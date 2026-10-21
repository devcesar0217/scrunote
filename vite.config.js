import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";
import { bunny } from "laravel-vite-plugin/fonts";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig(({ mode }) => {
    // Local Windows/Linux development works without a private LAN hostname.
    // A reverse-proxied Vite server can explicitly opt in to its public URL.
    const env = loadEnv(mode, process.cwd(), "VITE_");
    const devUrl = env.VITE_DEV_SERVER_URL
        ? new URL(env.VITE_DEV_SERVER_URL)
        : null;

    return {
        plugins: [
            laravel({
                input: ["resources/css/app.css", "resources/js/app.js"],
                refresh: true,
                fonts: [
                    bunny("Instrument Sans", {
                        weights: [400, 500, 600],
                        optimizedFallbacks: false,
                    }),
                ],
            }),
            tailwindcss(),
        ],
        server: {
            host: "0.0.0.0",
            port: 5173,
            strictPort: true,
            origin: "https://scrunote.10.231.68.25.nip.io",
    
            allowedHosts: ["scrunote.10.231.68.25.nip.io"],
    
            hmr: {
                host: "scrunote.10.231.68.25.nip.io",
                protocol: "wss",
                clientPort: 443,
                path: "/vite-hmr",
            },
            watch: {
                ignored: ["**/storage/framework/views/**"],
            },
        },
    };
});
