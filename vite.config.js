import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

// `npm run dev:mobile` routeert assets en HMR via Tailscale Serve (poort 8443),
// zodat de site op gsm werkt via https://<tailscaleHost>. Vereist eenmalig per
// machine: tailscale serve --bg --https=8443 http://127.0.0.1:5173, plus de
// Host-rewrite proxy in ~/Library/Application Support/Herd/config/valet/Nginx/.
const tailscaleHost = "arnauds-macbook-pro.tailcfa200.ts.net";
const mobile = process.env.MOBILE === "1";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/site.css", "resources/js/site.js"],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
        ...(mobile && {
            // Vite bindt standaard aan "localhost", wat op macOS naar ::1
            // resolvet. `tailscale serve` proxyt naar 127.0.0.1:5173 en krijgt
            // dan connection refused (502 op elke asset). Forceer IPv4.
            host: "127.0.0.1",
            allowedHosts: [tailscaleHost],
            cors: { origin: [`https://${tailscaleHost}`, /\.test$/] },
            hmr: {
                protocol: "wss",
                host: tailscaleHost,
                clientPort: 8443,
            },
        }),
    },
});
