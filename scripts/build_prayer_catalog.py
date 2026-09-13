"""Build the checked-in prayer directory from GeoNames dumps (no runtime API).

Usage: python3 scripts/build_prayer_catalog.py cities15000.zip alternateNamesV2.zip
Requires Babel for CLDR country names. Review the resulting JSON before deployment.
"""
import csv
import hashlib
import io
import json
import sys
import unicodedata
import re
import zipfile
from datetime import date
from pathlib import Path
from babel import Locale

LOCALES = ['en', 'ar', 'bn', 'hi', 'es', 'fr']
METHODS = {
    'MA': 'MOROCCO', 'DZ': 'ALGERIA', 'TN': 'TUNISIA', 'EG': 'EGYPT',
    'SA': 'MAKKAH', 'AE': 'GULF', 'QA': 'QATAR', 'KW': 'KUWAIT',
    'FR': 'FRANCE', 'TR': 'TURKEY', 'RU': 'RUSSIA', 'MY': 'JAKIM',
    'ID': 'KEMENAG', 'SG': 'SINGAPORE', 'PK': 'KARACHI', 'BD': 'KARACHI',
    'IN': 'KARACHI', 'AF': 'KARACHI', 'IR': 'TEHRAN', 'US': 'ISNA',
    'CA': 'ISNA', 'PT': 'PORTUGAL', 'JO': 'JORDAN',
}

def slug(value):
    value = unicodedata.normalize('NFKD', value).encode('ascii', 'ignore').decode()
    return re.sub('[^a-z0-9]+', '-', value.lower()).strip('-')

with zipfile.ZipFile(sys.argv[1]) as archive:
    rows = list(csv.reader(io.TextIOWrapper(archive.open('cities15000.txt')), delimiter='\t'))
selected = {r[0]: r for r in rows if r[8] != 'MA' and r[7] in
            ['PPL', 'PPLA', 'PPLA2', 'PPLA3', 'PPLA4', 'PPLC', 'PPLG']
            and (int(r[14]) >= 500000 or r[7] == 'PPLC')}
names = {}
with zipfile.ZipFile(sys.argv[2]) as archive:
    for r in csv.reader(io.TextIOWrapper(archive.open('alternateNamesV2.txt')), delimiter='\t'):
        if len(r) < 8 or r[1] not in selected or r[2] not in LOCALES or r[6] == '1' or r[7] == '1':
            continue
        key = (r[1], r[2])
        score = (r[4] == '1', r[5] != '1')
        if key not in names or score > names[key][0]:
            names[key] = (score, r[3])
countries = {}
for gid, r in sorted(selected.items(), key=lambda item: (item[1][8], item[1][2])):
    code = r[8]
    if code not in countries:
        localized = {loc: Locale.parse(loc).territories.get(code, code) for loc in LOCALES}
        countries[code] = {'names': localized, 'slugs': {
            loc: slug(localized['en' if loc in ['ar', 'bn', 'hi'] else loc]) for loc in LOCALES
        }, 'method': METHODS.get(code, 'MWL'),
            'school': 'HANAFI' if code in ['PK', 'BD', 'IN', 'AF', 'TR'] else 'STANDARD', 'cities': {}}
    city_slug = slug(r[2]) + '-' + gid
    countries[code]['cities'][city_slug] = {
        'geoname_id': int(gid), 'latitude': float(r[4]), 'longitude': float(r[5]),
        'timezone': r[17], 'names': {loc: names.get((gid, loc), (None, r[1]))[1] for loc in LOCALES},
    }
result = {'source': 'https://download.geonames.org/export/dump/',
          'license': 'https://creativecommons.org/licenses/by/4.0/',
          'generated_on': str(date.today()),
          'selection': 'Populated places with population >= 500000, plus national capitals; Morocco uses the existing curated catalog.',
          'sha256': {Path(p).name: hashlib.sha256(Path(p).read_bytes()).hexdigest() for p in sys.argv[1:3]},
          'countries': countries}
output = Path(__file__).resolve().parents[1] / 'resources/data/prayer-cities.json'
output.write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
print(f'{len(selected)} cities in {len(countries)} countries/territories; saved {output}')
