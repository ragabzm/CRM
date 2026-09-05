import { HelpArticleScreen } from "@/components/screens/portal/HelpArticleScreen";
import { PortalShell } from "@/components/shell/portal/PortalShell";

/** One article, by its stable id — never by a slug that a retitle would break. */
export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  return (
    <PortalShell signedIn={false}>
      <HelpArticleScreen id={id} />
    </PortalShell>
  );
}
