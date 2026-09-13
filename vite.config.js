import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import path from "path";

export default ({ mode }) => {
    const env = loadEnv(mode, process.cwd(), "VITE_");
    return defineConfig({
        define: {
            "process.env.IS_DEMO": JSON.stringify(env.VITE_IS_DEMO ?? "false"),
        },
        plugins: [
            laravel({
                input: ["resources/js/app.js"],
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
        ],

        resolve: {
            alias: {
                "@": path.resolve(__dirname, "./resources/js/views"),
                "~": path.resolve(__dirname, "./resources/js"),
            },
        },
    });
};
