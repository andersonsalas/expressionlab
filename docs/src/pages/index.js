import React, { useState } from 'react';
import clsx from 'clsx';
import Link from '@docusaurus/Link';
import useBaseUrl from '@docusaurus/useBaseUrl';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import { usePluginData } from '@docusaurus/useGlobalData';
import Layout from '@theme/Layout';
import Translate, { translate } from '@docusaurus/Translate';
import FlaskIcon from '@site/static/img/flask-icon.svg';
import ArchitectureIllustration from '@site/static/img/architecture-isometric.svg';
import styles from './index.module.css';

const FEATURES = [
  {
    key: 'queries',
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
    title: (
      <Translate id="homepage.features.readonly.title" description="Title for read-only feature">
        Read-Only by Default
      </Translate>
    ),
    detail: (
      <Translate id="homepage.features.readonly.detail" description="Detail for read-only feature">
        Operates in read-only mode with strict CPU time limits. Mirror heavy queries to in-memory SQLite.
      </Translate>
    ),
  },
  {
    key: 'credentials',
    title: (
      <Translate id="homepage.features.credentials.title" description="Title for credentials feature">
        Zero Database Credentials
      </Translate>
    ),
    detail: (
      <Translate id="homepage.features.credentials.detail" description="Detail for credentials feature">
        Access is authorized from wp-config.php and authenticated directly in your browser session.
      </Translate>
    ),
  },
];

const SHOWCASE_ITEMS = [
  {
    key: 'vegalite',
    badge: (
      <Translate id="homepage.showcase.vegalite.badge" description="Badge for Vega-Lite showcase">
        New
      </Translate>
    ),
    title: (
      <Translate id="homepage.showcase.vegalite.title" description="Title for Vega-Lite showcase">
        Vega-Lite visualizations
      </Translate>
    ),
    description: (
      <Translate id="homepage.showcase.vegalite.description" description="Description for Vega-Lite showcase">
        Render interactive charts from live data with built-in Vega-Lite integration.
      </Translate>
    ),
    image: '/img/showcase2.png',
  },
  {
    key: 'multitab',
    title: (
      <Translate id="homepage.showcase.multitab.title" description="Title for multi-tab showcase">
        Multi-tab interface
      </Translate>
    ),
    description: (
      <Translate id="homepage.showcase.multitab.description" description="Description for multi-tab showcase">
        Run multiple concurrent diagnostic workflows with complete independence.
      </Translate>
    ),
    image: '/img/showcase1.png',
  },
  {
    key: 'snippets',
    title: (
      <Translate id="homepage.showcase.snippets.title" description="Title for snippet library showcase">
        Snippet library
      </Translate>
    ),
    description: (
      <Translate id="homepage.showcase.snippets.description" description="Description for snippet library showcase">
        Organize frequently used snippets with local and cross-site persistence on Chromium browsers.
      </Translate>
    ),
    image: '/img/showcase3.png',
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
                Diagnostic environment for WordPress
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
                to="/download">
                <Translate id="homepage.hero.cta.download" description="Primary CTA button to download page">
                  Download Plugin
                </Translate>
              </Link>
              <Link
                className={clsx('button', styles.buttonSecondary)}
                to="/docs/getting-started/installation">
                <Translate id="homepage.hero.cta.start" description="Secondary CTA button to getting started">
                  Getting Started
                </Translate>
              </Link>
            </div>
            <div className={styles.heroNotice}>
              <FlaskIcon className={styles.heroNoticeIcon} aria-hidden="true" />
              <span>
                <Translate
                  id="homepage.hero.notice"
                  description="Notice below CTA buttons">
                  Built for local development and staging workflows.
                </Translate>
              </span>
            </div>
          </div>
          <div className={styles.heroVisualContainer}>
            <div className={styles.heroGraphicWrapper}>
              <ArchitectureIllustration
                className={styles.heroIllustration}
                role="img"
              />
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
              <h3 className={styles.featureTitle}>{feature.title}</h3>
              <p className={styles.featureDetail}>{feature.detail}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function ShowcaseSection() {
  const [activeTab, setActiveTab] = useState(0);
  const currentItem = SHOWCASE_ITEMS[activeTab] || SHOWCASE_ITEMS[0];
  const imageUrl = useBaseUrl(currentItem.image);

  return (
    <section className={styles.showcaseSection}>
      <div className="container">
        <div className={styles.showcaseHeader}>
          <h2 className={styles.showcaseTitle}>
            <Translate id="homepage.showcase.title" description="Main title for showcase section">
              A comprehensive diagnostic environment
            </Translate>
          </h2>
          <p className={styles.showcaseSubtitle}>
            <Translate id="homepage.showcase.subtitle" description="Subtitle for showcase section">
              Expression Lab integrates tools and APIs to make data exploration and diagnostics powerful and interactive.
            </Translate>
          </p>
        </div>

        <div className={styles.showcaseGrid}>
          <div className={styles.showcaseNav} role="tablist" aria-orientation="vertical">
            {SHOWCASE_ITEMS.map((item, index) => {
              const isActive = index === activeTab;
              return (
                <button
                  key={item.key}
                  type="button"
                  role="tab"
                  id={`showcase-tab-${item.key}`}
                  aria-selected={isActive}
                  aria-controls={`showcase-panel-${item.key}`}
                  tabIndex={isActive ? 0 : -1}
                  className={clsx(styles.showcaseTab, isActive && styles.showcaseTabActive)}
                  onClick={() => setActiveTab(index)}>
                  <div className={styles.showcaseTabHeader}>
                    <h3 className={styles.showcaseTabTitle}>{item.title}</h3>
                    {item.badge && (
                      <span className={styles.showcaseTabBadge}>{item.badge}</span>
                    )}
                  </div>
                  <p className={styles.showcaseTabDetail}>{item.description}</p>
                </button>
              );
            })}
          </div>

          <div className={styles.showcasePreviewWrapper}>
            <div
              id={`showcase-panel-${currentItem.key}`}
              role="tabpanel"
              aria-labelledby={`showcase-tab-${currentItem.key}`}
              className={styles.terminalWindow}>
              <div className={styles.terminalHeader}>
                <div className={styles.terminalDots} aria-hidden="true">
                  <span className={styles.terminalDot}></span>
                  <span className={styles.terminalDot}></span>
                  <span className={styles.terminalDot}></span>
                </div>
              </div>
              <div className={styles.terminalBody}>
                <img
                  src={imageUrl}
                  alt=""
                  className={styles.showcasePreviewImage}
                  width="1304"
                  height="729"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

function formatPostDate(dateString, currentLocale) {
  if (!dateString) return { month: '', day: '', year: '' };
  const date = new Date(dateString);
  const locale = currentLocale === 'es' ? 'es-ES' : 'en-US';

  let month = date.toLocaleDateString(locale, {
    month: 'short',
    timeZone: 'UTC',
  }).replace('.', '');

  month = month.charAt(0).toUpperCase() + month.slice(1);

  const day = date.toLocaleDateString(locale, {
    day: '2-digit',
    timeZone: 'UTC',
  });

  const year = date.toLocaleDateString(locale, {
    year: 'numeric',
    timeZone: 'UTC',
  });

  return { month, day, year };
}

function RecentPostsSection() {
  const { i18n } = useDocusaurusContext();
  const pluginData = usePluginData('docusaurus-plugin-content-blog');
  const recentPosts = pluginData?.recentPosts || [];

  if (!recentPosts.length) {
    return null;
  }

  return (
    <section className={styles.recentPostsSection}>
      <div className="container">
        <div className={styles.recentPostsHeader}>
          <h2 className={styles.recentPostsTitle}>
            <Translate
              id="homepage.recentPosts.title"
              description="Title for latest posts section on the homepage">
              Latest posts
            </Translate>
          </h2>
          <Link className={styles.viewAllLink} to="/blog">
            <Translate
              id="homepage.recentPosts.viewAll"
              description="Link to view all blog posts">
              View all posts →
            </Translate>
          </Link>
        </div>

        <div className={styles.recentPostsList}>
          {recentPosts.map((post) => {
            const { month, day, year } = formatPostDate(post.date, i18n.currentLocale);
            const author = post.authors?.[0];

            return (
              <article key={post.id || post.permalink} className={styles.postItem}>
                <div className={styles.postDateBadge} aria-label={`${day} ${month} ${year}`}>
                  <span className={styles.postDateMonth}>{month}</span>
                  <span className={styles.postDateDay}>{day}</span>
                  <span className={styles.postDateYear}>{year}</span>
                </div>

                <div className={styles.postBody}>
                  <h3 className={styles.postTitle}>
                    <Link to={post.permalink} className={styles.postTitleLink}>
                      {post.title}
                    </Link>
                  </h3>

                  {author && (
                    <div className={styles.postAuthorRow}>
                      {author.imageURL ? (
                        <img
                          src={author.imageURL}
                          alt={author.name}
                          className={styles.authorAvatar}
                          loading="lazy"
                        />
                      ) : (
                        <span className={styles.authorCircle} aria-hidden="true" />
                      )}
                      <span className={styles.authorName}>{author.name}</span>
                    </div>
                  )}

                  {post.description && (
                    <p className={styles.postExcerpt}>{post.description}</p>
                  )}
                </div>
              </article>
            );
          })}
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
        <ShowcaseSection />
        <RecentPostsSection />
      </main>
    </Layout>
  );
}
