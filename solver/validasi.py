#!/usr/bin/env python3
"""
Pemeriksa jadwal, tidak bergantung pada OR-Tools.

Dipakai oleh solver.py (untuk meringkas hasil) dan bisa dijalankan sendiri untuk memeriksa
jadwal mana pun, termasuk jadwal manual:

    python3 validasi.py contoh/input.json contoh/jadwal_asli.json

Jadwal = daftar blok: {ruang_id, guru_id, hari, jam_ke_mulai, jam_ke_selesai}.
"""
import json
import sys
from collections import Counter, defaultdict


def _sel(blok):
    """Semua (hari, jam_ke) yang dipakai sebuah blok."""
    return [(blok["hari"], j) for j in range(blok["jam_ke_mulai"], blok["jam_ke_selesai"] + 1)]


def _kosong(daftar_jam, terisi):
    """Jumlah jam kosong di antara jam terisi pertama dan terakhir (istirahat tidak dihitung, sebab bukan jam_ke)."""
    idx = [i for i, j in enumerate(daftar_jam) if j in terisi]
    if len(idx) < 2:
        return 0
    return (idx[-1] - idx[0] + 1) - len(idx)


def periksa(data, jadwal):
    beban = data["beban"]
    kelas_dari_ruang = {b["ruang_id"]: b["kelas_id"] for b in beban}
    idx_beban = {(b["ruang_id"], b["guru_id"]): i for i, b in enumerate(beban)}
    slot_kbm = {(h, j) for h, j in data["slots"]}
    hari_jam = defaultdict(list)
    for h, j in sorted(slot_kbm):
        hari_jam[h].append(j)
    ketersediaan = {(k["guru_id"], k["hari"], k["jam_ke"]): k["status"] for k in data.get("ketersediaan", [])}

    keras = []
    guru_sel, kelas_sel = defaultdict(list), defaultdict(list)
    blok_per_beban = defaultdict(list)

    for blok in jadwal:
        kunci = (blok["ruang_id"], blok["guru_id"])
        if kunci not in idx_beban:
            keras.append(f"Blok tanpa beban ajar: ruang {kunci[0]}, guru {kunci[1]}")
            continue
        bi = idx_beban[kunci]
        blok_per_beban[bi].append(blok)
        kelas = kelas_dari_ruang[blok["ruang_id"]]
        for sel in _sel(blok):
            if sel not in slot_kbm:
                keras.append(f"Beban {bi}: hari {sel[0]} jam {sel[1]} bukan jam KBM")
            guru_sel[(blok["guru_id"],) + sel].append(bi)
            kelas_sel[(kelas,) + sel].append(bi)
            if ketersediaan.get((blok["guru_id"],) + sel) == "tidak_bisa":
                keras.append(f"Guru {blok['guru_id']} dijadwalkan pada slot tidak_bisa (hari {sel[0]} jam {sel[1]})")

    for kunci, isi in guru_sel.items():
        if len(isi) > 1:
            keras.append(f"Bentrok guru {kunci[0]} hari {kunci[1]} jam {kunci[2]} ({len(isi)} blok)")
    for kunci, isi in kelas_sel.items():
        if len(isi) > 1:
            keras.append(f"Bentrok kelas {kunci[0]} hari {kunci[1]} jam {kunci[2]} ({len(isi)} blok)")

    for i, b in enumerate(beban):
        ada = sorted(x["jam_ke_selesai"] - x["jam_ke_mulai"] + 1 for x in blok_per_beban.get(i, []))
        harapan = sorted(b.get("pola_blok") or [b["jam_per_minggu"]])
        if ada != harapan:
            keras.append(f"Beban {i} (ruang {b['ruang_id']}, guru {b['guru_id']}): blok {ada}, seharusnya {harapan}")

    # ---- metrik kualitas (soft) ----
    hindari = sum(1 for k, isi in guru_sel.items() if ketersediaan.get(k) == "hindari")
    guru_kosong = 0
    for g in {k[0] for k in guru_sel}:
        for h, jams in hari_jam.items():
            guru_kosong += _kosong(jams, {j for (gg, hh, j) in guru_sel if gg == g and hh == h})
    kelas_kosong = 0
    for kls in {k[0] for k in kelas_sel}:
        for h, jams in hari_jam.items():
            kelas_kosong += _kosong(jams, {j for (kk, hh, j) in kelas_sel if kk == kls and hh == h})
    hari_sama = 0
    for i, blok in blok_per_beban.items():
        cacah = Counter(x["hari"] for x in blok)
        hari_sama += sum(v - 1 for v in cacah.values())

    return {
        "pelanggaran_keras": keras,
        "metrik": {"slot_hindari_terpakai": hindari, "jam_kosong_guru": guru_kosong,
                   "jam_kosong_kelas": kelas_kosong, "blok_di_hari_sama": hari_sama},
    }


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.exit("pakai: python3 validasi.py input.json jadwal.json")
    data = json.load(open(sys.argv[1], encoding="utf-8"))
    j = json.load(open(sys.argv[2], encoding="utf-8"))
    j = j["jadwal"] if isinstance(j, dict) else j
    hasil = periksa(data, j)
    print(f"{len(j)} blok diperiksa")
    print("Pelanggaran keras:", len(hasil["pelanggaran_keras"]))
    for p in hasil["pelanggaran_keras"][:20]:
        print("  -", p)
    print("Metrik:", json.dumps(hasil["metrik"], ensure_ascii=False))
    sys.exit(1 if hasil["pelanggaran_keras"] else 0)
