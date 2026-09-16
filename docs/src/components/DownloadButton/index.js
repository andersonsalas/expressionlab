import React from 'react';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import Translate from '@docusaurus/Translate';
import styles from './styles.module.css';

/**
 * Dynamic download button for the Expression Lab plugin.
 *
 * Constructs the GitHub Releases download URL from the plugin version
 * exposed via `docusaurus.config.js` → `customFields.pluginVersion`.
 *
 * Labels are fully localized via `@docusaurus/Translate`.
 */
export default function DownloadButton() {
  const { siteConfig } = useDocusaurusContext();
  const version = siteConfig.customFields.pluginVersion;
  const downloadUrl = `https://github.com/andersonsalas/expressionlab/releases/download/v${version}/expressionlab-${version}.zip`;

  return (
    <div className={styles.wrapper}>
      <a
        className="button button--primary button--lg installation-download-btn"
        href={downloadUrl}
        target="_blank"
        rel="noopener noreferrer"
        aria-label={`Download Expression Lab v${version} (ZIP)`}
      >
        <Translate
          id="installation.downloadButton"
          description="Label for the plugin download button on the installation page"
        >
          Download Expression Lab
        </Translate>
      </a>
      <div className={styles.version}>
        <Translate
          id="installation.currentVersion"
          description="Label indicating the current plugin version below the download button"
          values={{ version: `v${version}` }}
        >
          {'Current version: {version}'}
        </Translate>
      </div>
    </div>
  );
}
