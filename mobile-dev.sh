#!/bin/bash
# Start local dev server accessible from mobile devices on the same WiFi network.
# Usage: ./mobile-dev.sh
# Stop:  Ctrl+C (restores .env and vite.config.js automatically)

set -e

IP=$(ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null)
if [ -z "$IP" ]; then
  echo "❌ Kon geen lokaal IP-adres vinden. Ben je verbonden met WiFi?"
  exit 1
fi

PORT=8000

# Backup originals
cp .env .env.mobile-backup
cp vite.config.js vite.config.js.mobile-backup

restore() {
  echo ""
  echo "🔄 Originele configuratie herstellen..."
  mv .env.mobile-backup .env
  mv vite.config.js.mobile-backup vite.config.js
  echo "✅ Hersteld. Normale dev-omgeving is terug."
}
trap restore EXIT

# Update .env
sed -i '' "s|APP_URL=.*|APP_URL=http://${IP}:${PORT}|" .env

# Update vite.config.js — inject host + hmr config
sed -i '' "s|server: {|server: {\n        host: \"0.0.0.0\",\n        hmr: {\n            host: \"${IP}\",\n        },|" vite.config.js

echo "📱 Mobile dev server starten..."
echo ""
echo "   Open op je telefoon: http://${IP}:${PORT}"
echo ""
echo "   Druk Ctrl+C om te stoppen en alles te herstellen."
echo ""

# Start both servers, kill both on exit
php artisan serve --host="${IP}" --port="${PORT}" &
PHP_PID=$!
npm run dev &
VITE_PID=$!

wait $PHP_PID $VITE_PID
