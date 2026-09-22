async function testDirect() {
  const token = 'ruzel:CAA371384455A205D7DB';
  const tag = 'mushrooms';
  const url = `https://api.pinboard.in/v1/posts/get?auth_token=${token}&tag=${tag}`;
  
  try {
    console.log(`Fetching ${url}...`);
    const response = await fetch(url);
    const text = await response.text();
    console.log('Response text:', text);
  } catch (e) {
    console.error('Error:', e);
  }
}

testDirect();
