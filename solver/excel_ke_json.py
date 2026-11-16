#!/usr/bin/env python3
"""
Alat uji: ubah jadwal_terstruktur.xlsx menjadi input.json (data untuk solver) dan jadwal_asli.json
(jadwal manual sebagai pembanding). ID di sini buatan (urutan), bukan ID database.

    python3 excel_ke_json.py jadwal_terstruktur.xlsx contoh/
"""
import json
import re
import sys
from openpyxl import load_workbook

HARI = {"Senin": 1, "Selasa": 2, "Rabu": 3, "Kamis": 4, "Jumat": 5}
HS = {"Sen": 1, "Sel": 2, "Rab": 3, "Kam": 4, "Jum": 5}

wb = load_workbook(sys.argv[1], read_only=True)
baris = lambda n: list(wb[n].iter_rows(values_only=True))[1:]
out = sys.argv[2].rstrip("/")

slots = [[HARI[h], j] for h, j, m, s, jenis, ket in baris("waktu") if jenis == "KBM" and j is not None]

kelas, ruang, guru = {}, {}, {}
fid = lambda d, k: d.setdefault(k, len(d) + 1)
beban, jadwal = [], []
for rombel, mapel, kode, jam, jb, pola in baris("beban_ajar"):
    k, r, g = fid(kelas, rombel), fid(ruang, (rombel, mapel)), fid(guru, kode)
    blok = []
    for bagian in pola.split(";"):
        m = re.match(r"\s*(\w+) (\d+)j \(jam (\d+)(?:-(\d+))?\)", bagian)
        a, z = int(m.group(3)), int(m.group(4) or m.group(3))
        blok.append((HS[m.group(1)], a, z))
        jadwal.append({"ruang_id": r, "guru_id": g, "hari": HS[m.group(1)], "jam_ke_mulai": a, "jam_ke_selesai": z})
    beban.append({"ruang_id": r, "kelas_id": k, "guru_id": g, "jam_per_minggu": jam,
                  "pola_blok": [z - a + 1 for _, a, z in blok]})

data = {"slots": slots, "beban": beban, "ketersediaan": [], "terkunci": [],
        "pengaturan": {"batas_waktu_detik": 120, "workers": 8},
        "_peta": {"kelas": {v: k for k, v in kelas.items()}, "guru": {v: k for k, v in guru.items()}}}
json.dump(data, open(f"{out}/input.json", "w", encoding="utf-8"), ensure_ascii=False, indent=1)
json.dump(jadwal, open(f"{out}/jadwal_asli.json", "w", encoding="utf-8"), ensure_ascii=False)
print(len(slots), "slot,", len(beban), "beban,", len(jadwal), "blok")
