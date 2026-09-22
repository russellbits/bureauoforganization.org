async function testAll() {
  const token = 'ruzel:CAA371384455A205D7DB';
  const url = `https://api.pinboard.in/v1/posts/get?auth_token=${token}`;
  
  try {
    console.log(`Fetching all latest: ${url}...`);
    const response = await fetch(url);
    const text = await response.text();
    console.log('Response text:', text);
  } catch (e) {
    console.error('Error:', e);
  }
}

testAll();
