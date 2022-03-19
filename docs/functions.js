export const HookParams = ({ hook }) => {
  let params = hook.doc.tags.filter((tag) => tag.name === "param");

  let paramsOutput = params.map((oneParam, idx, arr) => {
    const paramTypesOut = oneParam.types.map((type) => {
      return <span>{type}</span>;
    });

    const comma = idx < arr.length - 1 ? ", " : "";

    return (
      <span>
        {paramTypesOut} {oneParam.variable}
        {comma}
      </span>
    );
  });

  const commaBeforeParams = paramsOutput.length ? ", " : "";

  return (
    <div>
      <code>
        do_action( '{hook.name}'{commaBeforeParams}
        {paramsOutput} )
      </code>
    </div>
  );
};
