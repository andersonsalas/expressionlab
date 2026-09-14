import React from 'react';
import Translate from '@docusaurus/Translate';
import styles from './styles.module.css';

export default function Footer() {
  const year = new Date().getFullYear();

  return (
    <footer className={styles.footer}>
      <div className={styles.container}>
        <p className={styles.paragraph}>
          <Translate
            id="footer.paragraph1"
            description="Footer copyright notice"
            values={{ year }}>
            {'© {year} Anderson Salas and contributors. All rights reserved.'}
          </Translate>
        </p>
        <p className={styles.paragraph}>
          <Translate
            id="footer.paragraph2"
            description="Footer WordPress trademark and non-affiliation disclaimer">
            WordPress® is a registered trademark of the WordPress Foundation. Expression Lab is not affiliated with, sponsored by, or endorsed by the WordPress Foundation.
          </Translate>
        </p>
        <p className={styles.paragraph}>
          <Translate
            id="footer.disclaimer"
            description="Footer experimental software and warranty disclaimer">
            {'Expression Lab is experimental software provided under the GPLv2 license "as is", without warranty of any kind. It is not audited by independent security firms and is strictly not intended for production, governmental, or mission-critical environments.'}
          </Translate>
        </p>
      </div>
    </footer>
  );
}
