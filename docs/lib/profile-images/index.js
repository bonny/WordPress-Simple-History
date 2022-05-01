const IMAGE_COUNT = 9
let images = []

for (let current_image = 1; current_image <= IMAGE_COUNT; current_image++) {
  images.push(require(`./profile-pic-${current_image}.svg`))
}

module.exports = images
