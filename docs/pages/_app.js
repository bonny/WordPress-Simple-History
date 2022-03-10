import Prism from "prism-react-renderer/prism";
import "nextra-theme-docs/style.css";

(typeof global !== "undefined" ? global : window).Prism = Prism;

require("prismjs/components/prism-javascript");
require("prismjs/components/prism-php");
require("prismjs/components/prism-sql");
require("prismjs/components/prism-jsx");

export default function Nextra({ Component, pageProps }) {
  return <Component {...pageProps} />;
}
