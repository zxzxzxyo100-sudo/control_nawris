/**
 * Nawras API gateway — CORS must run before any routes or proxies.
 *
 * Env:
 *   PORT                 (default 8787)
 *   UPSTREAM_ORIGIN      PHP / Apache root serving /api/*.php (e.g. http://127.0.0.1:8080)
 *   CORS_ORIGINS         Comma-separated browser origins allowed to call this gateway
 *                        (default: localhost Vite + nawris.nawras-ly.com)
 */
import express from "express";
import cors from "cors";
import { createProxyMiddleware } from "http-proxy-middleware";

const PORT = Number(process.env.PORT || 8787);
const UPSTREAM = (process.env.UPSTREAM_ORIGIN || "http://127.0.0.1:8080").replace(/\/$/, "");

const rawList = (process.env.CORS_ORIGINS ||
  "http://localhost:5173,http://127.0.0.1:5173,http://localhost:3000,https://nawris.nawras-ly.com")
  .split(",")
  .map((s) => s.trim())
  .filter(Boolean);

const allowAll = rawList.includes("*");
const allowedOrigins = new Set(rawList.filter((o) => o !== "*"));

const dynamicCors = cors({
  origin(origin, callback) {
    if (!origin) return callback(null, true);
    if (allowAll || allowedOrigins.has(origin)) return callback(null, true);
    return callback(null, false);
  },
  credentials: true,
  methods: ["GET", "HEAD", "PUT", "PATCH", "POST", "DELETE", "OPTIONS"],
  allowedHeaders: [
    "Content-Type",
    "Authorization",
    "X-Requested-With",
    "X-API-TOKEN",
    "X-Api-Token",
    "Accept",
  ],
  exposedHeaders: ["Content-Type"],
  optionsSuccessStatus: 204,
});

const app = express();

app.use(dynamicCors);
app.options("*", dynamicCors);

app.get("/health", (_req, res) => {
  res.json({ ok: true, upstream: UPSTREAM });
});

app.use(
  "/api",
  createProxyMiddleware({
    target: UPSTREAM,
    changeOrigin: true,
    secure: false,
    logLevel: "warn",
  })
);

app.use((_req, res) => {
  res.status(404).json({ ok: false, message: "Not found" });
});

app.listen(PORT, () => {
  console.log(`[nawris-gateway] http://127.0.0.1:${PORT}`);
  console.log(`[nawris-gateway] CORS origins: ${allowAll ? "*" : [...allowedOrigins].join(", ")}`);
  console.log(`[nawris-gateway] proxy /api -> ${UPSTREAM}/api`);
});
