/**
 * The configuration sections that exist.
 *
 * Not "everything the product will eventually configure": a section index that
 * lists destinations which do not exist teaches the administrator that half
 * the navigation is decorative. Each name here arrived with the screen behind
 * it — integrations last, with the ERP adapter it configures.
 */
export const ADMIN_SECTIONS = [
  "organisation",
  "channels",
  "knowledge",
  "ticketing",
  "autoAssignment",
  "serviceLevels",
  "email",
  "integrations",
  "platform",
  "auditLog",
] as const;

export type AdminSection = (typeof ADMIN_SECTIONS)[number];

/** URL segment for a section. The i18n key stays camelCase; the path is kebab. */
export const SECTION_PATHS: Record<AdminSection, string> = {
  organisation: "/admin/organisation",
  channels: "/admin/channels",
  knowledge: "/admin/knowledge",
  autoAssignment: "/admin/auto-assignment",
  ticketing: "/admin/ticketing",
  serviceLevels: "/admin/service-levels",
  email: "/admin/email",
  integrations: "/admin/integrations",
  platform: "/admin/platform",
  auditLog: "/admin/audit-log",
};
