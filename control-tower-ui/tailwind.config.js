/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{js,ts,jsx,tsx}"],
  theme: {
    extend: {
      fontFamily: {
        sans: ["Tajawal", "Inter", "system-ui", "sans-serif"],
        mono: ['"IBM Plex Mono"', "ui-monospace", "monospace"],
      },
      colors: {
        nw: {
          bg: "#09090b",
          card: "#131316",
          rim: "#27272e",
          mute: "#9898a4",
          pale: "#cbcbd4",
          white: "#ededf0",
          pur: "#8b5cf6",
          grn: "#10b981",
          orn: "#f59e0b",
          red: "#ef4444",
        },
      },
      boxShadow: {
        card: "0 4px 20px -4px rgba(12, 74, 110, 0.12), 0 8px 16px -8px rgba(15, 23, 42, 0.08)",
        cardDark: "0 4px 16px rgba(0,0,0,0.5)",
      },
    },
  },
  plugins: [],
};
