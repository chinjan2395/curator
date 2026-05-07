import axios from 'axios'

export async function fetchPreviewPosts(url, limit = 9) {
  const { data } = await axios.get(url, { params: { limit } })
  return data
}
