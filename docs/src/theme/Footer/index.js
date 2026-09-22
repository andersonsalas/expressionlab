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
      </div>
    </footer>
  );
}
