import PinboardModule from 'node-pinboard';
const Pinboard = PinboardModule.default || PinboardModule;

async function test() {
  const token = 'ruzel:BA86DF86EB615C72096A';
  const pinboard = new Pinboard(token);
  try {
    console.log('Fetching tag: mushrooms...');
    const response = await pinboard.get({ tag: 'mushrooms' });
    console.log('Response:', JSON.stringify(response, null, 2));
  } catch (e) {
    console.error('Error:', e);
  }
}

test();
