import { MDXProvider } from "@mdx-js/react"

const components = {
}

export const HookParams = ({ hook }) => {
  const params = hook.doc.tags.filter(tag => tag.name === 'param')

  const paramsOutput = params.map((oneParam, idx, arr) => {
    const paramTypesOut = oneParam.types.map((type, typeIdx, typeArr) => {
      const divider = typeIdx < typeArr.length - 1 ? '|' : ''
      return (
        <span>
          {type}
          {divider}
        </span>
      )
    })

    const comma = idx < arr.length ? ', ' : ''

    return (
      <span>
        {comma}
        {paramTypesOut} {oneParam.variable}
      </span>
    )
  })

  let paramsDoc = params.map((oneParam, idx, arr) => {
    const paramTypesOut = oneParam.types.map((type, typeIdx, typeArr) => {
      const divider = typeIdx < typeArr.length - 1 ? '|' : ''
      return (
        <span>
          {type}
          {divider}
        </span>
      )
    })

    if (!paramTypesOut.length) {
      return null
    }

    return (
      <li>
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

      <MDXProvider components={components}>
        {/* how to render markdown here? */}
      </MDXProvider>

      <div>{paramsDoc}</div>
    </div>
  )
}
