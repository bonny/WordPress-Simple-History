import Image from 'next/image'
import profilePic from './profile-pic.jpeg'

function LogItem({
  who = 'Jane Does',
  date = 'April 5, 2022 - 22:23 (25 days ago)',
  text = '',
  level = 'info'
}) {
  const Loglevel = function () {
    if (level === 'info') {
      return null
    }

    let colors = {
      emergency: {
        bgcolor: '#df0101',
        color: 'white'
      },
      alert: {
        bgcolor: 'rgb(199, 69, 69)',
        color: 'white'
      },
      critical: {
        bgcolor: '#fa5858',
        color: 'white'
      },
      error: {
        bgcolor: '#f79f81',
        color: 'black'
      },
      warning: {
        bgcolor: '#f7d358',
        color: 'black'
      },
      notice: {
        bgcolor: 'rgb(219, 219, 183)',
        color: 'black'
      },
      debug: {
        bgcolor: '#cef6d8',
        color: 'black'
      }
    }

    let classes = 'rounded text-xs px-1.5 py-1 ml-2 relative -top-.5'
    let styles = {
      color: colors[level].color,
      'background-color': colors[level].bgcolor
    }

    return (
      <span className={classes} style={styles}>
        {level}
      </span>
    )
  }

  return (
    <div className="bg-gray-200 p-4 rounded-lg">
      <div className="bg-white p-4 rounded-md">
        <div className="flex gap-6">
          <div>
            <p className="w-10 text-blue-500">
              <Image src={profilePic} layout="responsive" />
            </p>
          </div>
          <div>
            <p className="m-0">
              <span className="font-semibold">{who}</span>
              <span className="text-gray-400"> • </span>
              <span className="text-sky-700">{date}</span>
            </p>
            <p className="m-0" style={{ marginTop: 0 }}>
              {text}
              <Loglevel />
            </p>
          </div>
        </div>
      </div>
    </div>
  )
}

export default LogItem
