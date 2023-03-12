export const HookTOCItem = ({ hook }) => {
  return (
    <li key={`hook-toc-item-${hook.name}`}>
      <a href={`#${hook.name}`}>{hook.name}</a>
    </li>
  )
}

/**
 * Renders any examples for an hook.
 *
 * @param hook The hook data.
 * @returns
 */
function HookExamples({ hook }) {
  if (!hook.examples || !hook.examples.length) {
    return null
  }

  const examples_output = hook.examples.map(example => {
    return (
      <div key={example}>
        <pre>
          <code>{example}</code>
        </pre>
      </div>
    )
  })

  return (
    <>
      <h5>Example usage</h5>
      {examples_output}
    </>
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

      {hook.doc.description && <p>{hook.doc.description}</p>}

      {hook.doc.long_description_html && (
        <div
          className="mt-1"
          dangerouslySetInnerHTML={{ __html: hook.doc.long_description_html }}
        ></div>
      )}

      <HookParams hook={hook} />
      <HookExamples hook={hook} />
    </div>
  )
}

/**
 * Renders any parameters for a hook.
 */
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

  if (paramsDoc && paramsDoc.length) {
    paramsDoc = <ul>{paramsDoc}</ul>
  }

  let hook_function
  switch (hook.type) {
    case 'action':
      hook_function = 'do_action'
      break
    case 'filter':
      hook_function = 'apply_filters'
      break
    default:
      break
  }

  // https://github.com/mdx-js/mdx/issues/197
  //
  return (
    <>
      <pre>
        <code>
          {hook_function}( '{hook.name}'{paramsOutput} )
        </code>
      </pre>

      {paramsDoc}
    </>
  )
}
