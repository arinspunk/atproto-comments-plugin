// src/index.ts
import { AtprotoComments } from "./element.js";
export * from "./element.js";
if (typeof customElements !== "undefined" && !customElements.get("atproto-comments")) {
  customElements.define("atproto-comments", AtprotoComments);
}
