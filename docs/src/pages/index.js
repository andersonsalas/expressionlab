import React from 'react';
import clsx from 'clsx';
import Link from '@docusaurus/Link';
import useBaseUrl from '@docusaurus/useBaseUrl';
import Layout from '@theme/Layout';
import Translate, { translate } from '@docusaurus/Translate';
import styles from './index.module.css';

const FEATURES = [
  {
    key: 'queries',
    badge: (
      <Translate id="homepage.features.queries.badge" description="Badge for queries feature">
        01 // INSPECTION
      </Translate>
    ),
    title: (
      <Translate id="homepage.features.queries.title" description="Title for queries feature">
        Declarative AST Sandbox
      </Translate>
    ),
    detail: (
      <Translate id="homepage.features.queries.detail" description="Detail for queries feature">
        Inspect posts, options, tables, and files through structured syntax, eliminating disposable scripts and runtime risks.
      </Translate>
    ),
  },
  {
    key: 'readonly',
    badge: (
      <Translate id="homepage.features.readonly.badge" description="Badge for read-only feature">
        02 // GUARDRAILS
      </Translate>
    ),
    title: (
      <Translate id="homepage.features.readonly.title" description="Title for read-only feature">
        Read-Only by Default
      </Translate>
    ),
    detail: (
      <Translate id="homepage.features.readonly.detail" description="Detail for read-only feature">
        Operates in read-only mode with strict CPU time limits. Mirror heavy queries to in-memory SQLite to avoid overloading live MySQL.
      </Translate>
    ),
  },
  {
    key: 'credentials',
    badge: (
      <Translate id="homepage.features.credentials.badge" description="Badge for credentials feature">
        03 // ACCESS
      </Translate>
    ),
    title: (
      <Translate id="homepage.features.credentials.title" description="Title for credentials feature">
        Zero Database Credentials
      </Translate>
    ),
    detail: (
      <Translate id="homepage.features.credentials.detail" description="Detail for credentials feature">
        Access is authorized from wp-config.php and authenticated directly in your browser session. No passwords, tokens, or credentials are ever stored in the database.
      </Translate>
    ),
  },
];

function HeroSection() {
  return (
    <section className={styles.heroSection}>
      <div className="container">
        <div className={styles.heroGrid}>
          <div className={styles.heroContent}>
            <div className={styles.eyebrow}>
              <Translate id="homepage.hero.eyebrow" description="Eyebrow subtitle in hero">
                Developer tooling for WordPress
              </Translate>
            </div>
            <h1 className={styles.heroTitle}>
              <Translate id="homepage.hero.title" description="Main title on landing page">
                Expression Lab
              </Translate>
              <sup className={styles.heroAlpha}>
                <Translate id="homepage.hero.alpha" description="Alpha indicator next to main title">
                  Alpha
                </Translate>
              </sup>
            </h1>
            <p className={styles.heroDescription}>
              <Translate id="homepage.hero.description" description="Main description on landing page">
                An interactive console and declarative DSL to inspect WordPress internals safely without raw PHP or eval().
              </Translate>
            </p>
            <div className={styles.actions}>
              <Link
                className={clsx('button', styles.buttonPrimary)}
                to="/docs/getting-started/installation">
                <Translate id="homepage.hero.cta.start" description="Primary CTA button to getting started">
                  Getting Started
                </Translate>
              </Link>
              <Link
                className={clsx('button', styles.buttonSecondary)}
                href="https://github.com/andersonsalas/expressionlab">
                <Translate id="homepage.hero.cta.github" description="Secondary CTA button to github">
                  View on GitHub
                </Translate>
              </Link>
            </div>
            <div className={styles.heroNotice}>
              <Translate
                id="homepage.hero.notice"
                description="Alpha stage notice below CTA buttons">
                Alpha stage - Intended strictly for local development and staging inspection.
              </Translate>
            </div>
          </div>
          <div className={styles.heroVisualContainer}>
            <div className={styles.terminalWindow}>
              <div className={styles.terminalHeader}>
                <div className={styles.terminalDots}>
                  <span className={styles.terminalDot}></span>
                  <span className={styles.terminalDot}></span>
                  <span className={styles.terminalDot}></span>
                </div>
                <span className={styles.terminalTitle}>Expression Lab Console</span>
              </div>
              <div className={styles.terminalBody}>
                <img
                  src={useBaseUrl('/img/console.gif')}
                  alt="Expression Lab Diagnostics Console Interface"
                  className={styles.heroGif}
                  width="1493"
                  height="800"
                  loading="eager"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

function FeaturesSection() {
  return (
    <section className={styles.featuresSection}>
      <div className="container">
        <div className={styles.featuresGrid}>
          {FEATURES.map((feature) => (
            <div key={feature.key} className={styles.featureCard}>
              <div className={styles.featureBadge}>{feature.badge}</div>
              <h3 className={styles.featureTitle}>{feature.title}</h3>
              <p className={styles.featureDetail}>{feature.detail}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

export default function Home() {
  return (
    <Layout
      title={translate({
        id: 'homepage.meta.title',
        message: 'Expression Lab',
        description: 'Meta title for homepage',
      })}
      description={translate({
        id: 'homepage.meta.description',
        message:
          'An interactive diagnostics console and declarative DSL for WordPress. Inspect database tables, files, options, and posts through an AST sandbox without writing raw PHP or using eval().',
        description: 'Meta description for homepage',
      })}>
      <main className={styles.main}>
        <HeroSection />
        <FeaturesSection />
      </main>
    </Layout>
  );
}
