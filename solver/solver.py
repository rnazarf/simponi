#!/usr/bin/env python3
"""
Penyusun jadwal pelajaran dengan OR-Tools CP-SAT.

    python3 solver.py input.json output.json [--waktu 120] [--workers 8] [--log]

Input  : lihat contoh/input.json (dibuat oleh Laravel atau oleh excel_ke_json.py).
Output : {status, waktu_detik, objektif, jadwal:[...], pelanggaran_keras:[...], metrik:{...}, peringatan:[...]}
Exit   : 0 = ada jadwal (OPTIMAL/FEASIBLE), 2 = tidak ada jadwal (INFEASIBLE/UNKNOWN), 1 = kesalahan input.

Model
  Satu "blok" = beberapa jam pelajaran berurutan (jam_ke berurutan; istirahat boleh dilewati).
  Variabel  : x[blok, hari, jam_mulai] = 1 bila blok diletakkan di sana.
  Keras     : tiap blok tepat satu posisi; guru dan kelas tidak bentrok; slot 'tidak_bisa' tidak dipakai;
              blok 'dikunci' tetap; hanya jam KBM (upacara/senam tidak dipakai).
  Lunak     : slot 'hindari', jam kosong guru, jam kosong kelas, dua blok mapel-guru yang sama di hari yang sama.
"""
import argparse
import json
import sys
import time
from collections import defaultdict

from ortools.sat.python import cp_model

from validasi import periksa

BOBOT_BAWAAN = {"hindari": 3, "guru_kosong": 2, "kelas_kosong": 4, "hari_sama": 5}


def selesaikan(data, batas_waktu=120.0, workers=8, log=False):
    mulai = time.time()
    peringatan = []
    bobot = {**BOBOT_BAWAAN, **data.get("pengaturan", {}).get("bobot", {})}

    hari_jam = defaultdict(list)
    for hari, jam in data["slots"]:
        hari_jam[hari].append(jam)
    for h in hari_jam:
        hari_jam[h].sort()
    jam_set = {h: set(v) for h, v in hari_jam.items()}

    ketersediaan = {(k["guru_id"], k["hari"], k["jam_ke"]): k["status"] for k in data.get("ketersediaan", [])}
    beban = data["beban"]
    idx_beban = {(b["ruang_id"], b["guru_id"]): i for i, b in enumerate(beban)}

    # ---- pecah beban menjadi blok ----
    blok = []
    for bi, b in enumerate(beban):
        ukuran = b.get("pola_blok") or [b["jam_per_minggu"]]
        if sum(ukuran) != b["jam_per_minggu"]:
            peringatan.append(f"Beban {bi}: pola_blok {ukuran} tidak sama dengan jam_per_minggu {b['jam_per_minggu']}; pola dipakai")
        for u in ukuran:
            blok.append({"beban": bi, "ukuran": u, "tetap": None})

    # ---- blok terkunci: cocokkan dengan blok ber-ukuran sama milik beban yang sama ----
    for t in data.get("terkunci", []):
        bi = idx_beban.get((t["ruang_id"], t["guru_id"]))
        u = t["jam_ke_selesai"] - t["jam_ke_mulai"] + 1
        cocok = None
        if bi is not None:
            cocok = next((k for k in blok if k["beban"] == bi and k["ukuran"] == u and k["tetap"] is None), None)
        if cocok is None:
            peringatan.append(f"Blok terkunci tidak cocok dengan beban ajar mana pun: {t}")
            continue
        cocok["tetap"] = (t["hari"], t["jam_ke_mulai"])

    def kandidat(k):
        if k["tetap"]:
            return [k["tetap"]]
        g, u, hasil = beban[k["beban"]]["guru_id"], k["ukuran"], []
        for hari, jams in hari_jam.items():
            for s in jams:
                rentang = range(s, s + u)
                if all(j in jam_set[hari] for j in rentang) and \
                        not any(ketersediaan.get((g, hari, j)) == "tidak_bisa" for j in rentang):
                    hasil.append((hari, s))
        return hasil

    model = cp_model.CpModel()
    x = {}                       # (indeks blok, hari, jam mulai) -> BoolVar
    guru_cover = defaultdict(list)
    kelas_cover = defaultdict(list)
    suku = []                    # suku-suku fungsi tujuan (diminimalkan)

    for bk, k in enumerate(blok):
        b = beban[k["beban"]]
        pilihan = kandidat(k)
        if not pilihan:
            return {"status": "TIDAK_ADA_KANDIDAT", "waktu_detik": round(time.time() - mulai, 2), "jadwal": [],
                    "pelanggaran_keras": [f"Blok {k['ukuran']} jam milik guru {b['guru_id']} (ruang {b['ruang_id']}) tidak punya posisi valid "
                                          f"(terlalu panjang atau semua slot 'tidak_bisa')"],
                    "metrik": {}, "peringatan": peringatan}
        vs = []
        for hari, s in pilihan:
            v = model.NewBoolVar(f"x_{bk}_{hari}_{s}")
            x[(bk, hari, s)] = v
            vs.append(v)
            hindari = 0
            for j in range(s, s + k["ukuran"]):
                guru_cover[(b["guru_id"], hari, j)].append(v)
                kelas_cover[(b["kelas_id"], hari, j)].append(v)
                if ketersediaan.get((b["guru_id"], hari, j)) == "hindari":
                    hindari += 1
            if hindari and not k["tetap"]:
                suku.append(bobot["hindari"] * hindari * v)
        model.AddExactlyOne(vs)

    # ---- bentrok guru dan kelas ----
    for cover in (guru_cover, kelas_cover):
        for lst in cover.values():
            if len(lst) > 1:
                model.AddAtMostOne(lst)

    # ---- jam kosong (guru dan kelas) ----
    def jam_kosong(cover, bobot_kosong, nama):
        entitas = {k[0] for k in cover}
        for e in entitas:
            for hari, jams in hari_jam.items():
                n = len(jams)
                if n < 3:
                    continue
                occ = []
                for i, j in enumerate(jams):
                    o = model.NewBoolVar(f"occ_{nama}_{e}_{hari}_{i}")
                    lst = cover.get((e, hari, j), [])
                    model.Add(o == (sum(lst) if lst else 0))
                    occ.append(o)
                sebelum, sesudah = [None] * n, [None] * n   # ada jam terisi sebelum / sesudah posisi i
                sebelum[1] = occ[0]
                for i in range(2, n - 1):
                    b = model.NewBoolVar(f"bef_{nama}_{e}_{hari}_{i}")
                    model.Add(b >= occ[i - 1]); model.Add(b >= sebelum[i - 1]); model.Add(b <= occ[i - 1] + sebelum[i - 1])
                    sebelum[i] = b
                sesudah[n - 2] = occ[n - 1]
                for i in range(n - 3, 0, -1):
                    a = model.NewBoolVar(f"aft_{nama}_{e}_{hari}_{i}")
                    model.Add(a >= occ[i + 1]); model.Add(a >= sesudah[i + 1]); model.Add(a <= occ[i + 1] + sesudah[i + 1])
                    sesudah[i] = a
                for i in range(1, n - 1):
                    gap = model.NewBoolVar(f"gap_{nama}_{e}_{hari}_{i}")
                    model.Add(gap >= sebelum[i] + sesudah[i] - occ[i] - 1)
                    suku.append(bobot_kosong * gap)

    jam_kosong(guru_cover, bobot["guru_kosong"], "g")
    jam_kosong(kelas_cover, bobot["kelas_kosong"], "k")

    # ---- dua blok beban yang sama di hari yang sama (mis. B.Ing 2+2 jangan di hari yang sama) ----
    per_beban = defaultdict(list)
    for bk, k in enumerate(blok):
        per_beban[k["beban"]].append(bk)
    for bi, daftar in per_beban.items():
        if len(daftar) < 2:
            continue
        for hari in hari_jam:
            terms = [v for (bk, h, s), v in x.items() if bk in daftar and h == hari]
            if len(terms) < 2:
                continue
            dipakai = model.NewBoolVar(f"pakai_{bi}_{hari}")
            model.Add(dipakai <= sum(terms))
            suku.append(bobot["hari_sama"] * (sum(terms) - dipakai))

    if suku:
        model.Minimize(sum(suku))

    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = float(batas_waktu)
    solver.parameters.num_workers = int(workers)
    solver.parameters.random_seed = 2026
    solver.parameters.log_search_progress = bool(log)
    status = solver.Solve(model)
    nama_status = solver.StatusName(status)

    hasil = {"status": nama_status, "waktu_detik": round(time.time() - mulai, 2), "jadwal": [],
             "peringatan": peringatan, "pelanggaran_keras": [], "metrik": {}}
    if status not in (cp_model.OPTIMAL, cp_model.FEASIBLE):
        return hasil

    for (bk, hari, s), v in x.items():
        if solver.Value(v):
            k, b = blok[bk], beban[blok[bk]["beban"]]
            hasil["jadwal"].append({"ruang_id": b["ruang_id"], "guru_id": b["guru_id"], "hari": hari,
                                    "jam_ke_mulai": s, "jam_ke_selesai": s + k["ukuran"] - 1,
                                    "dikunci": bool(k["tetap"])})
    hasil["jadwal"].sort(key=lambda r: (r["hari"], r["jam_ke_mulai"], r["ruang_id"]))
    hasil["objektif"] = solver.ObjectiveValue() if suku else 0
    hasil["batas_bawah"] = solver.BestObjectiveBound() if suku else 0
    hasil.update(periksa(data, hasil["jadwal"]))   # verifikasi mandiri atas hasil solver
    return hasil


def main():
    p = argparse.ArgumentParser()
    p.add_argument("input"); p.add_argument("output")
    p.add_argument("--waktu", type=float); p.add_argument("--workers", type=int); p.add_argument("--log", action="store_true")
    a = p.parse_args()
    try:
        data = json.load(open(a.input, encoding="utf-8"))
        cfg = data.get("pengaturan", {})
        hasil = selesaikan(data, a.waktu or cfg.get("batas_waktu_detik", 120), a.workers or cfg.get("workers", 8), a.log)
    except (KeyError, ValueError, OSError) as e:
        json.dump({"status": "ERROR_INPUT", "pesan": repr(e), "jadwal": []}, open(a.output, "w", encoding="utf-8"))
        print("Kesalahan input:", repr(e), file=sys.stderr)
        sys.exit(1)
    json.dump(hasil, open(a.output, "w", encoding="utf-8"), ensure_ascii=False)
    print(f"status={hasil['status']} waktu={hasil['waktu_detik']}s blok={len(hasil['jadwal'])} "
          f"keras={len(hasil.get('pelanggaran_keras', []))} metrik={hasil.get('metrik')}")
    sys.exit(0 if hasil["jadwal"] else 2)


if __name__ == "__main__":
    main()
