#!/usr/bin/env node

import FtpDeploy from 'ftp-deploy';
import dotenv from 'dotenv';
import path from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs/promises';

dotenv.config();

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ftpDeploy = new FtpDeploy();

// Enhanced deployment with better file filtering
async function enhancedDeploy() {
  const config = {
    user: process.env.FTP_USERNAME,
    password: process.env.FTP_PASSWORD,
    host: process.env.FTP_HOST,
    port: parseInt(process.env.FTP_PORT || '21', 10),
    localRoot: path.join(__dirname, '../build'),
    remoteRoot: process.env.FTP_PATH || '/public_html',
    include: ['*', '**/*'],
    deleteRemote: false,
    forcePasv: true,
    // Exclude common temporary files that don't need to be deployed
    exclude: ['**/*.tmp', '**/.DS_Store', '**/Thumbs.db', '**/.git/**', '**/node_modules/**'],
    // Enable parallel uploads for better performance
    parallel: 4,
  };

  console.log(`🔍 Deploying build files from ${config.localRoot}`);
  console.log(`🌐 To FTP server: ${config.host}:${config.remoteRoot}`);

  // First, check that build directory exists and has content
  try {
    const buildStats = await fs.stat(config.localRoot);
    if (!buildStats.isDirectory()) {
      throw new Error('Build directory is not a directory');
    }

    const buildFiles = await fs.readdir(config.localRoot);
    if (buildFiles.length === 0) {
      console.log('⚠️  Warning: Build directory is empty');
      return;
    }

    console.log(`📁 Found ${buildFiles.length} files in build directory`);

    // Add progress tracking
    ftpDeploy.on('uploading', (data) => {
      if (data.filename) {
        // Show progress with percentage
        const percentage = Math.round((data.transferredFileCount / data.totalFilesCount) * 100);
        console.log(`📝 Uploading: ${data.filename} (${percentage}%)`);
      }
    });

    ftpDeploy.on('uploaded', (data) => {
      if (data.type === 'file' && data.filename) {
        console.log(`✅ Uploaded: ${data.filename}`);
      }
    });

    // Perform the deployment
    await ftpDeploy.deploy(config);

    console.log('\n✅ Deployment completed successfully!');

  } catch (error) {
    console.error('❌ Deployment failed:', error.message);
    process.exit(1);
  }
}

enhancedDeploy().catch(err => {
  console.error('💥 Fatal error:', err);
  process.exit(1);
});
