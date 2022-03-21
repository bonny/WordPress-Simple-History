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

    return (
      <li>
        {paramTypesOut} {oneParam.variable} {oneParam.content}{' '}
      </li>
    )
  })

  if (paramsDoc) {
    paramsDoc = <ul>{paramsDoc}</ul>
  }

  return (
    <div>
      <code>
        do_action( '{hook.name}'{paramsOutput} )
      </code>

      <div>{paramsDoc}</div>
    </div>
  )
}
