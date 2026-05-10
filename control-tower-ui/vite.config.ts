import { defineConfig, loadEnv } from "vite";
import react from "@vitejs/plugin-react";

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), "");
  const phpUpstream = env.VITE_PHP_UPSTREAM || "http://127.0.0.1:8080";

  return {
    plugins: [react()],
    server: {
      proxy: {
        // Same path as production `LOG_API_BASE` → `api/*.php` via Express gateway or Apache
        "/api": {
          target: phpUpstream,
          changeOrigin: true,
          secure: false,
        },
      },
    },
  };
});
