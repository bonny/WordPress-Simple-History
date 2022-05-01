import Image from 'next/image'
import profilePics from './profile-images/'

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

  const randomProfilePictureNumber = Math.floor(
    Math.random() * profilePics.length
  )
  const profilePic = profilePics[randomProfilePictureNumber]

  return (
    <div className="bg-gray-200 p-4 rounded-lg">
      <div className="bg-white p-4 rounded-md">
        <div className="flex gap-5">
          <div>
            <p className="w-12 text-blue-500">
              <Image src={profilePic} layout="responsive" />
            </p>
          </div>
          <div>
            <p className="m-0 text-sm">
              <span className="font-semibold">{who}</span>
              <span className="text-gray-400"> • </span>
              <span className="text-sky-700">{date}</span>
            </p>
            <p className="m-0 text-lg" style={{ marginTop: '.25rem' }}>
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
