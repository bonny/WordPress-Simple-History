export default {
  projectLink: "https://github.com/bonny/WordPress-Simple-History/", // GitHub link in the navbar
  github: "https://github.com/bonny/WordPress-Simple-History/",
  docsRepositoryBase:
    "https://github.com/bonny/WordPress-Simple-History/edit/feature/docs/docs/pages/", // base URL for the docs repository
  titleSuffix: " – Simple History Documentation",
  nextLinks: true,
  prevLinks: true,
  search: true,
  customSearch: null, // customizable, you can use algolia for example
  darkMode: true,
  footer: true,
  footerText: `${new Date().getFullYear()} © Pär Thernström.`,
  footerEditLink: `Edit this page on GitHub`,
  logo: (
    <>
      <img src="https://ps.w.org/simple-history/assets/icon.svg" alt="" />
      <div className="ml-2">
        <span className="font-semibold">Simple History</span> Documentation
      </div>
    </>
  ),
  head: (
    <>
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <meta
        name="description"
        content="Documentation for WordPress plugin Simple History"
      />
      <meta name="og:title" content="Simple History Documentation" />
    </>
  ),
  unstable_faviconGlyph: "📄",
};
