#!/bin/zsh
cd "$(dirname "$0")"
if ! command -v php >/dev/null 2>&1; then
  echo "PHP fehlt. Mit Homebrew installieren: brew install php"
  exit 1
fi
php storage/init.php
echo ""
echo "Website: http://127.0.0.1:8080"
echo "Admin:   http://127.0.0.1:8080/login/"
echo "Login:   admin@svfrohnstetten.local"
echo "Passwort: SVF-Test-2026!"
echo "Beenden: CTRL+C"
php -S 127.0.0.1:8080 -t public
