"""
repackage.py — bump the patch version and rebuild bmg-interactive-map.zip

Usage:
    python3 repackage.py          # bumps patch:  1.1.0 → 1.1.1
    python3 repackage.py --minor  # bumps minor:  1.1.0 → 1.2.0
    python3 repackage.py --major  # bumps major:  1.1.0 → 2.0.0
"""

import os
import re
import sys
import zipfile

SCRIPT_DIR  = os.path.dirname( os.path.abspath( __file__ ) )
PLUGIN_DIR  = SCRIPT_DIR
PLUGIN_FILE = os.path.join( PLUGIN_DIR, 'bmg-interactive-map.php' )
ZIP_OUT     = None  # set after version is determined

EXCLUDE_DIRS  = { '.claude', 'lib' }
EXCLUDE_EXTS  = { '.zip' }
EXCLUDE_FILES = { 'repackage.py' }

# ── 1. Read current version ───────────────────────────────────────────────────

with open( PLUGIN_FILE, 'r', encoding='utf-8' ) as f:
    source = f.read()

header_match = re.search( r'(\* Version:\s+)(\d+)\.(\d+)\.(\d+)', source )
define_match = re.search( r"(define\( 'BMG_MAP_VERSION',\s+')(\d+)\.(\d+)\.(\d+)(')", source )

if not header_match or not define_match:
    sys.exit( 'ERROR: could not find version strings in ' + PLUGIN_FILE )

major, minor, patch = int( header_match.group(2) ), int( header_match.group(3) ), int( header_match.group(4) )
old_version = f'{major}.{minor}.{patch}'

# ── 2. Determine new version ──────────────────────────────────────────────────

if '--major' in sys.argv:
    major += 1; minor = 0; patch = 0
elif '--minor' in sys.argv:
    minor += 1; patch = 0
else:
    patch += 1

new_version = f'{major}.{minor}.{patch}'
ZIP_OUT     = f'bmg-interactive-map-{new_version}.zip'

# ── 3. Write updated version into the PHP file ────────────────────────────────

source = source.replace(
    header_match.group(0),
    header_match.group(1) + new_version,
    1
)
source = source.replace(
    define_match.group(0),
    define_match.group(1) + new_version + define_match.group(5),
    1
)

with open( PLUGIN_FILE, 'w', encoding='utf-8' ) as f:
    f.write( source )

print( f'Version: {old_version} → {new_version}' )

# ── 4. Build zip ──────────────────────────────────────────────────────────────

if os.path.exists( ZIP_OUT ):
    os.remove( ZIP_OUT )

file_count = 0
with zipfile.ZipFile( ZIP_OUT, 'w', zipfile.ZIP_DEFLATED ) as zf:
    for root, dirs, files in os.walk( PLUGIN_DIR ):
        dirs[:] = [ d for d in dirs if d not in EXCLUDE_DIRS ]
        for fname in files:
            if any( fname.endswith( ext ) for ext in EXCLUDE_EXTS ):
                continue
            if fname in EXCLUDE_FILES:
                continue
            abs_path = os.path.join( root, fname )
            rel      = os.path.relpath( abs_path, PLUGIN_DIR )
            zf.write( abs_path, 'bmg-interactive-map/' + rel.replace( os.sep, '/' ) )
            file_count += 1

print( f'Built:   {ZIP_OUT}  ({file_count} files)' )
