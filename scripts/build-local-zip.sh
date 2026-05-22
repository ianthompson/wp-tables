#!/usr/bin/env sh
set -eu

root_dir=$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)
build_dir="$root_dir/build"
package_dir="$build_dir/wp-tables"
zip_file="$root_dir/wp-tables.zip"

rm -rf "$build_dir"
mkdir -p "$package_dir"
cp -R "$root_dir/assets" "$root_dir/readme.txt" "$root_dir/wp-tables.php" "$package_dir/"

rm -f "$zip_file"
(
	cd "$build_dir"
	zip -qr "$zip_file" wp-tables
)

rm -rf "$build_dir"
printf 'Built %s\n' "$zip_file"
