import localFont from "next/font/local";

/*
 * Self-hosted IBM Plex, SIL OFL 1.1 (licence committed at
 * public/fonts/ibm-plex/LICENSE.txt).
 *
 * next/font/local — never next/font/google: the intake forbids an external font
 * CDN in the request path, and a Google Fonts loader would put one there.
 */

export const plexSans = localFont({
  src: [
    { path: "../public/fonts/ibm-plex/IBMPlexSans-400.woff2", weight: "400", style: "normal" },
    { path: "../public/fonts/ibm-plex/IBMPlexSans-500.woff2", weight: "500", style: "normal" },
    { path: "../public/fonts/ibm-plex/IBMPlexSans-600.woff2", weight: "600", style: "normal" },
    { path: "../public/fonts/ibm-plex/IBMPlexSans-700.woff2", weight: "700", style: "normal" },
  ],
  variable: "--font-plex-sans",
  display: "swap",
  fallback: ["system-ui", "sans-serif"],
});

/**
 * Plex Sans Arabic ships its own matched Latin and figure set. Both subsets are
 * declared at each weight so that `TKT-000123` inside Arabic prose is drawn by
 * this family rather than falling through to a differently-proportioned face.
 */
export const plexSansArabic = localFont({
  src: [
    {
      path: "../public/fonts/ibm-plex/IBMPlexSansArabic-400.woff2",
      weight: "400",
      style: "normal",
    },
    {
      path: "../public/fonts/ibm-plex/IBMPlexSansArabic-latin-400.woff2",
      weight: "400",
      style: "normal",
    },
    {
      path: "../public/fonts/ibm-plex/IBMPlexSansArabic-500.woff2",
      weight: "500",
      style: "normal",
    },
    {
      path: "../public/fonts/ibm-plex/IBMPlexSansArabic-latin-500.woff2",
      weight: "500",
      style: "normal",
    },
    {
      path: "../public/fonts/ibm-plex/IBMPlexSansArabic-600.woff2",
      weight: "600",
      style: "normal",
    },
    {
      path: "../public/fonts/ibm-plex/IBMPlexSansArabic-latin-600.woff2",
      weight: "600",
      style: "normal",
    },
    {
      path: "../public/fonts/ibm-plex/IBMPlexSansArabic-700.woff2",
      weight: "700",
      style: "normal",
    },
    {
      path: "../public/fonts/ibm-plex/IBMPlexSansArabic-latin-700.woff2",
      weight: "700",
      style: "normal",
    },
  ],
  variable: "--font-plex-sans-arabic",
  display: "swap",
  fallback: ["system-ui", "sans-serif"],
});
