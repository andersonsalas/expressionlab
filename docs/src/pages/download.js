import React, { useEffect } from 'react';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import Layout from '@theme/Layout';
import Link from '@docusaurus/Link';
import Translate, { translate } from '@docusaurus/Translate';
import FlaskIcon from '@site/static/img/flask-icon.svg';
import styles from './download.module.css';

function ChevronRightIcon(props) {
  return (
    <svg
      viewBox="0 0 16 28"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      {...props}>
      <polyline points="2 2 14 14 2 26" />
    </svg>
  );
}

export default function Download() {
  const { siteConfig } = useDocusaurusContext();
  const version = siteConfig.customFields.pluginVersion;
  const downloadUrl = `https://github.com/andersonsalas/expressionlab/releases/download/v${version}/expressionlab-${version}.zip`;

  useEffect(() => {
    // Initiate automatic download via hidden iframe
    const iframe = document.createElement('iframe');
    iframe.setAttribute('style', 'display:none;width:0;height:0;border:0;');
    iframe.setAttribute('aria-hidden', 'true');
    iframe.src = downloadUrl;
    document.body.appendChild(iframe);

    return () => {
      if (iframe.parentNode) {
        iframe.parentNode.removeChild(iframe);
      }
    };
  }, [downloadUrl]);

  return (
    <Layout
      title={translate({
        id: 'download.meta.title',
        message: 'Download Expression Lab',
        description: 'Meta title for download page',
      })}
      description={translate({
        id: 'download.meta.description',
        message: 'Download the latest version of the Expression Lab WordPress plugin.',
        description: 'Meta description for download page',
      })}>
      <main className={styles.downloadMain}>
        <div className={styles.downloadContainer}>
          <h1 className={styles.downloadTitle}>
            <Translate
              id="download.title"
              description="Main title on download page">
              Thanks for downloading Expression Lab
            </Translate>
          </h1>

          <p className={styles.downloadText}>
            <Translate
              id="download.notice"
              description="Notice about automatic download with direct link fallback"
              values={{
                directLink: (
                  <a
                    href={downloadUrl}
                    className={styles.downloadLink}
                    target="_blank"
                    rel="noopener noreferrer">
                    <Translate
                      id="download.notice.directLink"
                      description="Direct link anchor text on download page">
                      direct link
                    </Translate>
                  </a>
                ),
              }}>
              {"Your download will begin shortly. If it doesn't work, try this {directLink}."}
            </Translate>
          </p>

          <p className={styles.downloadText}>
            <Translate
              id="download.help"
              description="Help prompt with link to Quick Start section"
              values={{
                quickStart: (
                  <Link
                    to="/docs/getting-started/quick-start"
                    className={styles.downloadLink}>
                    <Translate
                      id="download.help.quickStart"
                      description="Quick Start link text on download page">
                      Quick Start
                    </Translate>
                  </Link>
                ),
              }}>
              {'Have questions? Check out the {quickStart} section.'}
            </Translate>
          </p>

          <Link
            to="/docs/security/security-and-environment"
            className={styles.alphaNoticeCard}>
            <FlaskIcon className={styles.alphaIcon} aria-hidden="true" />
            <div className={styles.alphaContent}>
              <h2 className={styles.alphaTitle}>
                <Translate
                  id="download.alpha.title"
                  description="Alpha notice card title">
                  Alpha Release & Security Model
                </Translate>
              </h2>
              <p className={styles.alphaDescription}>
                <Translate
                  id="download.alpha.description"
                  description="Notice informing about experimental and alpha status of Expression Lab">
                  Explore the security architecture, safeguards, and sandbox design.
                </Translate>
              </p>
            </div>
            <ChevronRightIcon className={styles.alphaChevron} aria-hidden="true" />
          </Link>
        </div>
      </main>
    </Layout>
  );
}
