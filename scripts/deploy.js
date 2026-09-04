import FtpDeploy from 'ftp-deploy';
import dotenv from 'dotenv';
import path from 'path';
import { fileURLToPath } from 'url';

dotenv.config();

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ftpDeploy = new FtpDeploy();

const config = {
  user: process.env.FTP_USERNAME,
  password: process.env.FTP_PASSWORD,
  host: process.env.FTP_HOST,
  port: parseInt(process.env.FTP_PORT || '21', 10),
  // Resolves /dist relative to the root of your project directory
  localRoot: path.join(__dirname, '../build'),
  remoteRoot: process.env.FTP_PATH || '/public_html',
  include: ['*', '**/*'],
  deleteRemote: false,
  forcePasv: true,
};

ftpDeploy.on('uploading', (data) => {
  console.log(`[${data.transferredFileCount}/${data.totalFilesCount}] Uploading: ${data.filename}`);
});

console.log(`Deploying ${config.localRoot} to ${config.host}:${config.remoteRoot}...`);

ftpDeploy
  .deploy(config)
  .then(() => console.log('\nDeployment completed successfully!'))
  .catch((err) => console.error('\nDeployment failed:', err));
