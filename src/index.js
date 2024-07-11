// Entrypoint used by wp-scripts start and build.
import domReady from "@wordpress/dom-ready";
import { createRoot, render } from "@wordpress/element";
import MainGui from "./MainGui";

domReady(() => {
  const target = document.getElementById("simple-history-react-root");

  if (target) {
    if (createRoot) {
      createRoot(target).render(<MainGui />);
    } else {
      render(<MainGui />, target);
    }
  }
});
