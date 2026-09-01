import { ESLint } from "eslint";

/*
 * One ESLint instance for the whole run.
 *
 * Constructing ESLint resolves eslint.config.mjs, which pulls in
 * eslint-config-next — expensive enough that doing it per assertion (~50 times
 * across these files) blew the default 5s test timeout on a cold cache and made
 * the suite flaky. The instance is stateless across lintText calls, so sharing
 * it is safe as well as fast.
 */
let instance: ESLint | undefined;

function eslint(): ESLint {
  instance ??= new ESLint({ cwd: process.cwd() });
  return instance;
}

/**
 * Lints a source string as if it were a file at `filePath`, using the project's
 * real eslint.config.mjs.
 *
 * The path matters: two of the three rules are scoped by directory, so a
 * fixture "written" to components/screens/ exercises different rules from one
 * written to app/.
 */
export async function lintFixture(filePath: string, source: string) {
  const results = await eslint().lintText(source, { filePath });

  return results[0]?.messages ?? [];
}

export function messagesFrom(
  messages: Awaited<ReturnType<typeof lintFixture>>,
  ruleId: string,
) {
  return messages.filter((message) => message.ruleId === ruleId);
}
