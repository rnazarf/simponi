<?php

namespace App\Services;

use App\Models\Pengaturan;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\StreamHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiQuizService
{
    /**
     * Generate daftar soal menggunakan Google Gemini API.
     *
     * @param array{
     *   prompt: string,
     *   jumlah?: int,
     *   jenis?: string,
     *   tingkat?: string,
     *   opsi_count?: int,
     *   mapel?: string,
     *   jenjang?: string,
     *   kelas?: string,
     *   api_key?: string,
     *   model?: string
     * } $params
     * @return array{ok: bool, soal?: array, pesan?: string, model?: string}
     */
    public function generate(array $params): array
    {
        $apiKey = trim($params['api_key'] ?? '') ?: Pengaturan::geminiApiKey();
        if (empty($apiKey)) {
            return [
                'ok' => false,
                'pesan' => 'Google Gemini API Key belum diatur. Silakan atur di menu Pengaturan Sekolah atau file .env (GEMINI_API_KEY).',
            ];
        }

        $modelAwal = trim($params['model'] ?? '') ?: Pengaturan::geminiModel();
        // Fallback model teruji bila model pilihan (misal gemini-1.5-flash) sudah tidak didukung di API key tersebut
        $daftarModel = array_values(array_unique([$modelAwal, 'gemini-2.5-flash', 'gemini-flash-latest', 'gemini-2.5-pro']));

        $jumlah = max(1, min(30, (int) ($params['jumlah'] ?? 5)));
        $jenis = in_array($params['jenis'] ?? '', ['pg', 'esai', 'campuran'], true) ? $params['jenis'] : 'pg';
        $tingkat = in_array($params['tingkat'] ?? '', ['mudah', 'sedang', 'sulit', 'campuran'], true) ? $params['tingkat'] : 'sedang';
        $opsiCount = (int) ($params['opsi_count'] ?? 5);
        if (!in_array($opsiCount, [4, 5], true)) {
            $opsiCount = 5;
        }

        $promptUser = trim($params['prompt'] ?? '');
        if (empty($promptUser)) {
            return [
                'ok' => false,
                'pesan' => 'Prompt materi atau instruksi soal tidak boleh kosong.',
            ];
        }

        $systemPrompt = $this->bangunSystemPrompt(
            jumlah: $jumlah,
            jenis: $jenis,
            tingkat: $tingkat,
            opsiCount: $opsiCount,
            mapel: $params['mapel'] ?? null,
            jenjang: $params['jenjang'] ?? null,
            kelas: $params['kelas'] ?? null
        );

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => $systemPrompt . "\n\n### MATERI / INSTRUKSI DARI GURU:\n" . $promptUser,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.7,
            ],
        ];

        $terakhirError = '';

        try {
            $client = new Client([
                'handler' => new StreamHandler(),
                'timeout' => 60,
                'http_errors' => false,
            ]);

            foreach ($daftarModel as $model) {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

                $response = $client->post($url, [
                    'json' => $payload,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $body = (string) $response->getBody();
                $json = json_decode($body, true);

                if ($statusCode !== 200) {
                    $errorMsg = $json['error']['message'] ?? "Kode respon: {$statusCode}";
                    $status = $json['error']['status'] ?? '';
                    $terakhirError = $errorMsg;

                    // Jika model tidak ditemukan / sudah dideprecate, coba model berikutnya
                    if ($statusCode === 404 || str_contains($errorMsg, 'is not found for API version') || str_contains($errorMsg, 'not supported for generateContent')) {
                        Log::info("Model {$model} tidak tersedia, mencoba model cadangan berikutnya...");
                        continue;
                    }

                    if ($statusCode === 400 && (str_contains($errorMsg, 'API_KEY_INVALID') || stripos($errorMsg, 'API key not valid') !== false)) {
                        return [
                            'ok' => false,
                            'pesan' => 'API Key Google Gemini tidak valid. Periksa kembali API Key Anda di Pengaturan Sekolah atau browser.',
                        ];
                    }

                    if ($statusCode === 429 || $status === 'RESOURCE_EXHAUSTED') {
                        return [
                            'ok' => false,
                            'pesan' => 'Batas kuota permintaan API Google Gemini tercapai (Rate Limit / Quota Exceeded). Silakan coba 1 menit lagi.',
                        ];
                    }

                    Log::warning("Gemini API Error ({$model}): " . $body);
                    return [
                        'ok' => false,
                        'pesan' => 'Gagal memproses ke Google Gemini: ' . $errorMsg,
                    ];
                }

                $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if (!$rawText) {
                    continue;
                }

                $daftarSoal = $this->ekstrakDanValidasiSoal($rawText, $opsiCount);
                if (empty($daftarSoal)) {
                    continue;
                }

                return [
                    'ok' => true,
                    'soal' => $daftarSoal,
                    'model' => $model,
                    'total' => count($daftarSoal),
                ];
            }

            return [
                'ok' => false,
                'pesan' => 'Gagal memproses ke Google Gemini: ' . ($terakhirError ?: 'Model tidak merespon.'),
            ];
        } catch (Throwable $e) {
            Log::error('GeminiQuizService exception: ' . $e->getMessage());
            return [
                'ok' => false,
                'pesan' => 'Terjadi kesalahan saat menghubungi Google Gemini: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Susun instruksi prompt untuk model Gemini.
     */
    private function bangunSystemPrompt(
        int $jumlah,
        string $jenis,
        string $tingkat,
        int $opsiCount,
        ?string $mapel,
        ?string $jenjang,
        ?string $kelas
    ): string {
        $hurufOpsi = array_slice(['A', 'B', 'C', 'D', 'E'], 0, $opsiCount);
        $daftarHurufStr = implode(', ', $hurufOpsi);

        $konteks = [];
        if ($mapel) $konteks[] = "Mata Pelajaran: {$mapel}";
        if ($jenjang) $konteks[] = "Jenjang Pendidikan: {$jenjang}";
        if ($kelas) $konteks[] = "Kelas: {$kelas}";
        $konteksStr = $konteks ? "Konteks Belajar:\n- " . implode("\n- ", $konteks) . "\n" : "";

        $aturanJenis = match ($jenis) {
            'pg' => "Semua soal WAJIB berjenis 'pg' (Pilihan Ganda) dengan tepat {$opsiCount} opsi jawaban ({$daftarHurufStr}) dan 1 kunci jawaban yang benar.",
            'esai' => "Semua soal WAJIB berjenis 'esai' (Uraian/Esai). Untuk esai, field 'opsi' dan 'kunci' bernilai null.",
            'campuran' => "Buat sebagian besar soal berjenis 'pg' (Pilihan Ganda dengan {$opsiCount} opsi: {$daftarHurufStr}) dan sebagian kecil soal berupa 'esai' (analisis/uraian).",
        };

        $aturanTingkat = match ($tingkat) {
            'mudah' => "Tingkat kesulitan: MUDAH (LOTS - mengingat dan memahami konsep dasar).",
            'sulit' => "Tingkat kesulitan: SULIT / HOTS (Analisis, evaluasi, pemecahan masalah konteks nyata).",
            'sedang' => "Tingkat kesulitan: SEDANG (MOTS - penerapan konsep dan pemahaman terarah).",
            'campuran' => "Tingkat kesulitan: BERVARIASI (kombinasi 30% Mudah, 50% Sedang, 20% HOTS/Sulit).",
        };

        return <<<PROMPT
Anda adalah asisten ahli pembuat soal kuis dan evaluasi belajar berstandar Kurikulum Nasional Indonesia.
Tugas Anda adalah membuat tepat {$jumlah} butir soal berkualitas tinggi berdasarkan materi dan instruksi guru di bawah ini.

{$konteksStr}
Panduan Pembuatan Soal:
1. {$aturanJenis}
2. {$aturanTingkat}
3. Bahasa Indonesia baku, jelas, saintifik, dan tidak ambigu.
4. Untuk soal Pilihan Ganda (pg):
   - Pilihan pengecoh (distractor) harus masuk akal dan berhubungan dengan materi.
   - Tentukan satu huruf kunci jawaban yang paling tepat ('kunci').
   - Nilai bobot bawaan = 1.
5. Untuk soal Esai (esai):
   - Buat pertanyaan esai yang menguji pemahaman konsep atau analisis.
   - Nilai bobot bawaan = 5.
   - Opsi dan kunci bernilai null.
6. Sertakan pembahasan singkat ('pembahasan') untuk menjelaskan mengapa jawaban tersebut benar atau poin utama jawaban esai.

OUTPUT HARUS BERUPA ARRAY JSON MURNI dengan struktur schema persis seperti ini:
[
  {
    "jenis": "pg",
    "pertanyaan": "Teks pertanyaan lengkap...",
    "opsi": {
      "A": "Teks opsi A",
      "B": "Teks opsi B",
      "C": "Teks opsi C",
      "D": "Teks opsi D"
    },
    "kunci": "A",
    "bobot": 1,
    "pembahasan": "Penjelasan singkat jawaban benar..."
  },
  {
    "jenis": "esai",
    "pertanyaan": "Jelaskan proses...",
    "opsi": null,
    "kunci": null,
    "bobot": 5,
    "pembahasan": "Poin-poin penting jawaban..."
  }
]
PROMPT;
    }

    /**
     * Ekstrak JSON array dan validasi format soal.
     */
    private function ekstrakDanValidasiSoal(string $rawText, int $opsiCount): array
    {
        $clean = trim($rawText);

        // Hapus bungkus ```json ... ``` bila ada
        if (str_starts_with($clean, '```')) {
            $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
            $clean = preg_replace('/\s*```$/', '', $clean);
            $clean = trim($clean);
        }

        $decoded = json_decode($clean, true);
        if (!is_array($decoded)) {
            // Coba cari substring array JSON [ ... ]
            if (preg_match('/\[\s*\{.*\}\s*\]/s', $clean, $matches)) {
                $decoded = json_decode($matches[0], true);
            }
        }

        if (!is_array($decoded)) {
            return [];
        }

        // Jika model mengembalikan object yang membungkus array (misal {"soal": [...]})
        if (isset($decoded['soal']) && is_array($decoded['soal'])) {
            $decoded = $decoded['soal'];
        } elseif (isset($decoded['questions']) && is_array($decoded['questions'])) {
            $decoded = $decoded['questions'];
        }

        $hasil = [];
        $hurufDiharapkan = array_slice(['A', 'B', 'C', 'D', 'E'], 0, $opsiCount);

        foreach ($decoded as $item) {
            if (!is_array($item)) continue;

            $pertanyaan = trim((string) ($item['pertanyaan'] ?? $item['question'] ?? ''));
            if ($pertanyaan === '') continue;

            $jenis = strtolower(trim((string) ($item['jenis'] ?? $item['type'] ?? 'pg')));
            if (!in_array($jenis, ['pg', 'esai'], true)) {
                $jenis = !empty($item['opsi']) ? 'pg' : 'esai';
            }

            $bobot = (int) ($item['bobot'] ?? ($jenis === 'pg' ? 1 : 5));
            if ($bobot < 1) $bobot = $jenis === 'pg' ? 1 : 5;

            $pembahasan = trim((string) ($item['pembahasan'] ?? $item['explanation'] ?? ''));

            if ($jenis === 'esai') {
                $hasil[] = [
                    'jenis' => 'esai',
                    'pertanyaan' => $pertanyaan,
                    'opsi' => null,
                    'kunci' => null,
                    'bobot' => $bobot,
                    'pembahasan' => $pembahasan,
                ];
                continue;
            }

            // Normalisasi opsi pilihan ganda
            $rawOpsi = $item['opsi'] ?? $item['options'] ?? [];
            $opsiClean = [];

            if (is_array($rawOpsi)) {
                // Jika associative array {"A": "...", "B": "..."}
                $isAssoc = array_keys($rawOpsi) !== range(0, count($rawOpsi) - 1);
                if ($isAssoc) {
                    foreach ($rawOpsi as $k => $v) {
                        $key = strtoupper(trim((string) $k));
                        if (in_array($key, ['A', 'B', 'C', 'D', 'E'], true)) {
                            $opsiClean[$key] = trim((string) $v);
                        }
                    }
                } else {
                    // Indexed array ["Opsi 1", "Opsi 2", ...]
                    foreach ($rawOpsi as $idx => $v) {
                        if ($idx < 5) {
                            $huruf = ['A', 'B', 'C', 'D', 'E'][$idx];
                            $opsiClean[$huruf] = trim((string) $v);
                        }
                    }
                }
            }

            // Minimal harus ada 2 opsi
            if (count($opsiClean) < 2) continue;

            // Normalisasi kunci jawaban
            $kunci = strtoupper(trim((string) ($item['kunci'] ?? $item['answer'] ?? $item['correct_answer'] ?? '')));
            if (!isset($opsiClean[$kunci])) {
                // Jika kunci berupa teks kalimat jawaban, cocokkan dengan teks opsi
                foreach ($opsiClean as $h => $teksOpsi) {
                    if (strcasecmp($teksOpsi, $kunci) === 0) {
                        $kunci = $h;
                        break;
                    }
                }
            }

            // Jika masih belum valid, gunakan opsi pertama sebagai fallback
            if (!isset($opsiClean[$kunci])) {
                $kunci = array_key_first($opsiClean);
            }

            $hasil[] = [
                'jenis' => 'pg',
                'pertanyaan' => $pertanyaan,
                'opsi' => $opsiClean,
                'kunci' => $kunci,
                'bobot' => $bobot,
                'pembahasan' => $pembahasan,
            ];
        }

        return $hasil;
    }
}
