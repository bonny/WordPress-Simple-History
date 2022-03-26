export const HookTOCItem = ({ hook }) => {
  return (
    <li key={`hook-toc-item-${hook.name}`}>
      <a href={`#${hook.name}`}>{hook.name}</a>
    </li>
  )
}

/**
 * Show documentation for a single hook.
 *
 * @param hook Hook data
 * @returns
 */
export const Hook = ({ hook }) => {
  return (
    <div key={`hook-item-${hook.name}`} className="HookInformation">
      <hr />

      <h4 id={`${hook.name}`} className="HookInformation-name">
        {hook.name}
      </h4>

      <p>{hook.doc.description}</p>

      <div
        className="mt-1"
        dangerouslySetInnerHTML={{ __html: hook.doc.long_description_html }}
      ></div>

      <HookParams hook={hook} />
    </div>
  )
}

export const HookParams = ({ hook }) => {
  const params = hook.doc.tags.filter(tag => tag.name === 'param')

  const paramsOutput = params.map((oneParam, idx, arr) => {
    const paramTypesOut = oneParam.types.map((type, typeIdx, typeArr) => {
      const divider = typeIdx < typeArr.length - 1 ? '|' : ''
      return (
        <span key={`params-${type}`}>
          {type}
          {divider}
        </span>
      )
    })

    const comma = idx < arr.length ? ', ' : ''

    return (
      <span key={`hook-param-${oneParam.variable}`}>
        {comma}
        {paramTypesOut} {oneParam.variable}
      </span>
    )
  })

  let paramsDoc = params.map((oneParam, idx, arr) => {
    const paramTypesOut = oneParam.types.map((type, typeIdx, typeArr) => {
      const divider = typeIdx < typeArr.length - 1 ? '|' : ''
      return (
        <span key={`hook-param-type-${type}`}>
          {type}
          {divider}
        </span>
      )
    })

    if (!paramTypesOut.length) {
      return null
    }

    return (
      <li key={`hook-param-hook-${hook.name}-param-${oneParam.variable}`}>
        {paramTypesOut} {oneParam.variable} {oneParam.content}{' '}
      </li>
    )
  })

  if (paramsDoc) {
    paramsDoc = <ul>{paramsDoc}</ul>
  }

  // https://github.com/mdx-js/mdx/issues/197
  //
  return (
    <div>
      <code>
        do_action( '{hook.name}'{paramsOutput} )
      </code>

      <div>{paramsDoc}</div>
    </div>
  )
}
