// ---------------------------------------------------------------------------
// Load Web Awesome translations so internal strings (e.g. clear button) match
// the site locale. See https://webawesome.com/docs/localization
//
// Use explicit imports per locale so Vite only emits chunks for keys we load.
// Keys must match `webAwesomeLocale` from PHP (TypesenseConfig::webAwesomeLocale).
// ---------------------------------------------------------------------------

/**
 * Registers a clearer Swedish string for the search field clear control.
 */
async function patchSvClearEntryLabel(): Promise<void> {
  const { registerTranslation } = await import(
    "@awesome.me/webawesome/dist/utilities/localize.js"
  );
  const i18n = window.typesenseI18n as { clearSearchField?: string } | undefined;
  registerTranslation({
    $code: "sv",
    $name: "Svenska",
    $dir: "ltr",
    clearEntry: i18n?.clearSearchField ?? "Rensa sökfältet",
  } as Parameters<typeof registerTranslation>[0]);
}

export async function loadWebAwesomeLocale(): Promise<void> {
  const raw =
    (typeof window !== "undefined" && window.typesenseConfig?.webAwesomeLocale) ||
    "";
  const key = String(raw).toLowerCase().trim();
  if (!key || key === "en") {
    return;
  }

  switch (key) {
    case "sv":
      await import("@awesome.me/webawesome/dist/translations/sv.js");
      await patchSvClearEntryLabel();
      return;
    case "en-gb":
      await import("@awesome.me/webawesome/dist/translations/en-gb.js");
      return;
    case "da":
      await import("@awesome.me/webawesome/dist/translations/da.js");
      return;
    case "nb":
      await import("@awesome.me/webawesome/dist/translations/nb.js");
      return;
    case "nn":
      await import("@awesome.me/webawesome/dist/translations/nn.js");
      return;
    case "fi":
      await import("@awesome.me/webawesome/dist/translations/fi.js");
      return;
    case "de":
      await import("@awesome.me/webawesome/dist/translations/de.js");
      return;
    case "fr":
      await import("@awesome.me/webawesome/dist/translations/fr.js");
      return;
    case "es":
      await import("@awesome.me/webawesome/dist/translations/es.js");
      return;
    case "nl":
      await import("@awesome.me/webawesome/dist/translations/nl.js");
      return;
    case "pl":
      await import("@awesome.me/webawesome/dist/translations/pl.js");
      return;
    case "pt":
      await import("@awesome.me/webawesome/dist/translations/pt.js");
      return;
    case "it":
      await import("@awesome.me/webawesome/dist/translations/it.js");
      return;
    default:
      return;
  }
}
