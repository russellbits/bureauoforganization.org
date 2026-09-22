async function testDates() {
  const token = 'ruzel:CAA371384455A205D7DB';
  
  const dates = [
    '2021-01-01',
    '2021-06-01',
    '2021-12-01',
    '2022-01-01'
  ];

  for (const date of dates) {
    const url = `https://api.pinboard.in/v1/posts/get?auth_token=${token}&dt=${date}`;
    try {
      console.log(`Fetching date ${date}...`);
      const response = await fetch(url);
      const text = await response.text();
      console.log(`Response for ${date}: ${text}`);
    } catch (e) {
      console.error(`Error fetching ${date}:`, e);
    }
  }
}

testDates();
