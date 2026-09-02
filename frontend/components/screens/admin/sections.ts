/**
 * The six configuration sections.
 *
 * Six, not "everything the product will eventually configure". Knowledge base,
 * AI, the customer portal and integrations are deliberately absent: a section
 * index that lists destinations which do not exist teaches the administrator
 * that half the navigation is decorative.
 */
export const ADMIN_SECTIONS = [
  "organisation",
  "ticketing",
  "serviceLevels",
  "email",
  "platform",
  "auditLog",
] as const;

export type AdminSection = (typeof ADMIN_SECTIONS)[number];

/** URL segment for a section. The i18n key stays camelCase; the path is kebab. */
export const SECTION_PATHS: Record<AdminSection, string> = {
  organisation: "/admin/organisation",
  ticketing: "/admin/ticketing",
  serviceLevels: "/admin/service-levels",
  email: "/admin/email",
  platform: "/admin/platform",
  auditLog: "/admin/audit-log",
};
