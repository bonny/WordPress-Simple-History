export default {
  projectLink: 'https://github.com/bonny/WordPress-Simple-History/', // GitHub link in the navbar
  github: 'https://github.com/bonny/WordPress-Simple-History/',
  docsRepositoryBase:
    'https://github.com/bonny/WordPress-Simple-History/tree/main/docs/pages', // base URL for the docs repository
  titleSuffix: ' – Simple History Developer Documentation',
  nextLinks: true,
  prevLinks: true,
  search: true,
  customSearch: null, // customizable, you can use algolia for example
  unstable_flexsearch: true,
  unstable_staticImage: true,
  floatTOC: true,
  darkMode: true,
  footer: true,
  footerText: (
    <div>
      <p>Simple History is {`${new Date().getFullYear()} © Pär Thernström.`}</p>
      <p>
        <a href="https://www.freepik.com/vectors/people-profile">
          People profile vector created by freepik - www.freepik.com
        </a>
      </p>
    </div>
  ),
  footerEditLink: `Edit this page on GitHub`,
  logo: (
    <>
      <img src="https://ps.w.org/simple-history/assets/icon.svg" alt="" />
      <div className="ml-2">
        <span className="font-semibold">Simple History</span> Developer Documentation
      </div>
    </>
  ),
  head: (
    <>
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <meta
        name="description"
        content="Developer Documentation for WordPress plugin Simple History"
      />
      <meta name="og:title" content="Simple History Developer Documentation" />
    </>
  ),
  unstable_faviconGlyph: '📄'
}
