import React from 'react'

function LogItems(props) {
  console.log({ props })

  return (
    <div className="bg-gray-200 p-4 rounded-lg">
      <div className="bg-white p-4">
        {props.children.map(child => {
          return React.cloneElement(child, {
            overview: true,
          })
        })}
      </div>
    </div>
  )
}

export default LogItems
