// ---------------------------------------------------------------------------
// Search-statistics telemetry
// ---------------------------------------------------------------------------

import type { SearchStatisticsConfig } from "./types";

type SearchSurface = "quick" | "regular";

const STORAGE_KEY = "typesenseSearchStatisticsSession";
const CONSENT_EVENT = "typesense-search:statistics-consent";

interface PendingSearch {
  query: string;
  found: number;
  surface: SearchSurface;
}

function createSessionId(): string | null {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) {
    return crypto.randomUUID().replace(/-/g, "");
  }

  if (typeof crypto === "undefined" || !("getRandomValues" in crypto)) {
    return null;
  }

  const values = new Uint8Array(24);
  crypto.getRandomValues(values);
  return Array.from(values, (value) => value.toString(16).padStart(2, "0")).join("");
}

function characterLength(value: string): number {
  return Array.from(value.trim()).length;
}

/**
 * A per-entry-point tracker. The session identifier is only created after the
 * optional consent gate has opened, and is removed again on withdrawal.
 */
export function createSearchStatisticsTracker(
  config: SearchStatisticsConfig | undefined,
): { track: (query: string, found: number, surface: SearchSurface) => void; flush: () => void } {
  let consentGranted = !config?.requireConsent || window.typesenseSearchStatisticsConsent === true;
  let timer: ReturnType<typeof setTimeout> | undefined;
  let pending: PendingSearch | undefined;

  const clearSession = (): void => {
    try {
      sessionStorage.removeItem(STORAGE_KEY);
    } catch {
      // Storage can be blocked by browser privacy settings. Logging will simply
      // remain unavailable in that case instead of falling back to an ID.
    }
  };

  // Do not leave a previously created statistics identifier behind when the
  // administrator disables logging or turns on a consent gate.
  if (!config?.enabled || !consentGranted) {
    clearSession();
  }

  const getSessionId = (): string | null => {
    if (!config?.enabled || !consentGranted) return null;

    try {
      let sessionId = sessionStorage.getItem(STORAGE_KEY);
      if (!sessionId) {
        sessionId = createSessionId();
        if (!sessionId) return null;
        sessionStorage.setItem(STORAGE_KEY, sessionId);
      }
      return sessionId;
    } catch {
      return null;
    }
  };

  const send = (event: PendingSearch): void => {
    const sessionId = getSessionId();
    if (!config?.enabled || !sessionId || !config.endpoint) return;

    const payload = JSON.stringify({
      query: event.query,
      found: Math.max(0, Math.floor(event.found)),
      surface: event.surface,
      session_id: sessionId,
    });

    void fetch(config.endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: payload,
      keepalive: true,
      credentials: "same-origin",
    }).catch(() => {
      // Statistics must never interfere with the search experience.
    });
  };

  const flush = (): void => {
    if (timer) clearTimeout(timer);
    timer = undefined;

    if (!pending || !consentGranted) {
      pending = undefined;
      return;
    }

    const event = pending;
    pending = undefined;
    send(event);
  };

  window.addEventListener(CONSENT_EVENT, ((event: CustomEvent<{ granted?: boolean }>) => {
    const granted = event.detail?.granted === true;
    consentGranted = !config?.requireConsent || granted;

    if (!consentGranted) {
      pending = undefined;
      if (timer) clearTimeout(timer);
      timer = undefined;
      clearSession();
    }
  }) as EventListener);

  return {
    track(query: string, found: number, surface: SearchSurface): void {
      if (!config?.enabled || !consentGranted || characterLength(query) < (config.minimumChars ?? 3)) {
        return;
      }

      pending = { query, found, surface };
      if (timer) clearTimeout(timer);
      timer = setTimeout(flush, config.delayMs ?? 1000);
    },
    flush,
  };
}
