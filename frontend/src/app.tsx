import { useState, useEffect } from 'preact/hooks';
import { motion, AnimatePresence } from 'motion/react';
import './app.css';
import { appConfig } from './config/config';
import type { Account } from './config/config';
import { getStatus, formatStatus, sortByStatus, allOffline } from './availability';
import type { Availability, Status } from './availability';

// Accounts saved before business hours existed are always online.
const ALWAYS_ONLINE: Availability = { mode: 'always_online' };
// Re-evaluated periodically so the status changes on time while the page stays open.
const STATUS_REFRESH_MS = 30000;

/* ------------------------------------------------------------------
   Icons
------------------------------------------------------------------ */
function WaIcon({ size = 26 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" />
      <path d="M12 0C5.373 0 0 5.373 0 12c0 2.126.555 4.12 1.524 5.855L0 24l6.337-1.502A11.947 11.947 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.886 0-3.645-.497-5.166-1.365l-.371-.214-3.761.892.937-3.656-.235-.386A9.96 9.96 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z" />
    </svg>
  );
}

function CloseIcon() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
      <line x1="18" y1="6" x2="6" y2="18" />
      <line x1="6" y1="6" x2="18" y2="18" />
    </svg>
  );
}

function ChevronRightIcon() {
  return (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <polyline points="9 18 15 12 9 6" />
    </svg>
  );
}

/* ------------------------------------------------------------------
   Helpers
------------------------------------------------------------------ */
function waUrl(number: string, message: string): string {
  const clean = number.replace(/\D/g, '');
  return `https://wa.me/${clean}${message ? '?text=' + encodeURIComponent(message) : ''}`;
}

function iconSize(size: string): number {
  if (size === 'small') return 22;
  if (size === 'large') return 32;
  return 26;
}

/* ------------------------------------------------------------------
   ContactItem
------------------------------------------------------------------ */
type ContactItemProps = Account & { index: number; status: Status; statusText: string | null };

function ContactItem({ name, number, predefined_message, tagline, thumbnail, index, status, statusText }: ContactItemProps) {
  const { appearance } = appConfig;
  const displayName = name || number;
  const letterInitial = displayName.charAt(0).toUpperCase();

  return (
    <motion.a
      href={waUrl(number, predefined_message)}
      target="_blank"
      rel="noopener noreferrer"
      class="wpwc-contact"
      layout
      initial={{ opacity: 0, x: -10 }}
      animate={{ opacity: 1, x: 0 }}
      transition={{
        delay: 0.08 + index * 0.055,
        duration: 0.28,
        ease: [0.4, 0, 0.2, 1],
      }}
    >
      <span
        class="wpwc-contact-avatar"
        style={thumbnail ? {} : { background: appearance.color_bg, color: appearance.color_fg }}
      >
        {thumbnail
          ? <img src={thumbnail} alt={displayName} class="wpwc-contact-avatar-img" />
          : letterInitial
        }
        <span
          class={`wpwc-contact-avatar-dot${status.online ? '' : ' wpwc-contact-avatar-dot--offline'}`}
          aria-hidden="true"
        />
      </span>
      <span class="wpwc-contact-info">
        {tagline && (
          <span class="wpwc-contact-subtitle">{tagline}</span>
        )}
        <span class="wpwc-contact-name">{displayName}</span>
        {statusText !== null && (
          <span class={`wpwc-contact-status wpwc-contact-status--${status.online ? 'online' : 'offline'}`}>
            {statusText}
          </span>
        )}
      </span>
      <span class="wpwc-contact-cta" style={{ color: appearance.color_bg }}>
        {appConfig.appearance.cta_text}
        <ChevronRightIcon />
      </span>
    </motion.a>
  );
}

/* ------------------------------------------------------------------
   App
------------------------------------------------------------------ */
export function App() {
  const { appearance, accounts, timezone, weekdays } = appConfig;

  const [isOpen, setIsOpen] = useState(false);
  const [isVisible, setIsVisible] = useState(Number(appearance.appear_delay) === 0);
  const [showTooltip, setShowTooltip] = useState(false);

  useEffect(() => {
    const delay = Number(appearance.appear_delay);
    if (delay > 0) {
      const t = setTimeout(() => setIsVisible(true), delay * 1000);
      return () => clearTimeout(t);
    }
  }, []);

  const [now, setNow] = useState(() => new Date());

  useEffect(() => {
    const refresh = () => setNow(new Date());
    // Browsers pause timers in background tabs; refresh as soon as the tab is visible again.
    const onVisible = () => { if (document.visibilityState === 'visible') refresh(); };
    const t = setInterval(refresh, STATUS_REFRESH_MS);
    document.addEventListener('visibilitychange', onVisible);
    return () => {
      clearInterval(t);
      document.removeEventListener('visibilitychange', onVisible);
    };
  }, []);

  const statusTexts = {
    online: appearance.status_online,
    offline: appearance.status_offline,
    back: appearance.status_back,
    today: appearance.status_today,
    tomorrow: appearance.status_tomorrow,
  };
  const entries = sortByStatus((accounts ?? []).map(a => ({
    item: a,
    status: getStatus(a.availability ?? ALWAYS_ONLINE, timezone, now),
  })));
  const everyoneOffline = allOffline(entries.map(e => e.status));

  // Close panel on outside click
  useEffect(() => {
    if (!isOpen) return;
    const handler = (e: MouseEvent) => {
      const root = document.getElementById('wp-whatsapp-multi-chat-wrapper');
      if (root && !root.contains(e.target as Node)) setIsOpen(false);
    };
    document.addEventListener('click', handler);
    return () => document.removeEventListener('click', handler);
  }, [isOpen]);

  const corner = appearance.position_corner ?? 'bottom-right';
  const [v, h] = corner.split('-'); // 'bottom'/'top', 'right'/'left'
  const isBottom = v === 'bottom';
  const isRight = h === 'right';

  const widgetStyle = {
    [v]: `${appearance.offset_y}px`,
    [h]: `${appearance.offset_x}px`,
    // button first in DOM → column puts it top, column-reverse puts it bottom
    flexDirection: (isBottom ? 'column-reverse' : 'column') as 'column' | 'column-reverse',
    alignItems: (isRight ? 'flex-end' : 'flex-start') as 'flex-end' | 'flex-start',
  };

  const widgetClasses = [
    'wpwc-widget',
    appearance.hide_mobile  ? 'wpwc-hide-mobile'  : '',
    appearance.hide_desktop ? 'wpwc-hide-desktop' : '',
  ].filter(Boolean).join(' ');

  const btnClasses = [
    'wpwc-btn',
    `wpwc-btn--${appearance.button_size}`,
    appearance.button_style === 'icon-text' ? 'wpwc-btn--pill' : '',
    appearance.animation !== 'none' ? `wpwc-btn--${appearance.animation}` : '',
  ].filter(Boolean).join(' ');

  // Panel animates from the corner where the button is
  const panelOrigin = `${isBottom ? 'bottom' : 'top'} ${isRight ? 'right' : 'left'}`;

  return (
    <motion.div
      class={widgetClasses}
      data-h={h}
      style={{ ...widgetStyle, pointerEvents: isVisible ? 'auto' : 'none' }}
      initial={{ opacity: 0, y: isBottom ? 16 : -16 }}
      animate={{ opacity: isVisible ? 1 : 0, y: isVisible ? 0 : (isBottom ? 16 : -16) }}
      transition={{ duration: 0.5, ease: [0.34, 1.26, 0.64, 1] }}
    >

      {/* Main button — first in DOM so column-reverse puts it at screen edge */}
      <button
        class={btnClasses}
        style={{ background: appearance.color_bg, color: appearance.color_fg }}
        onClick={() => { setIsOpen(o => !o); setShowTooltip(false); }}
        onMouseEnter={() => setShowTooltip(true)}
        onMouseLeave={() => setShowTooltip(false)}
        aria-label={everyoneOffline ? `${appearance.aria_open} — ${appearance.status_offline}` : appearance.aria_open}
        aria-expanded={isOpen}
      >
        <AnimatePresence mode="wait" initial={false}>
          {isOpen && appearance.button_style !== 'icon-text' ? (
            <motion.span
              key="close"
              class="wpwc-btn-icon"
              initial={{ rotate: -90, opacity: 0, scale: 0.4 }}
              animate={{ rotate: 0,   opacity: 1, scale: 1   }}
              exit={{    rotate:  90, opacity: 0, scale: 0.4 }}
              transition={{ duration: 0.22, ease: [0.4, 0, 0.2, 1] }}
            >
              <CloseIcon />
            </motion.span>
          ) : (
            <motion.span
              key="wa"
              class="wpwc-btn-icon"
              initial={{ rotate:  90, opacity: 0, scale: 0.4 }}
              animate={{ rotate: 0,   opacity: 1, scale: 1   }}
              exit={{    rotate: -90, opacity: 0, scale: 0.4 }}
              transition={{ duration: 0.22, ease: [0.4, 0, 0.2, 1] }}
            >
              <WaIcon size={iconSize(appearance.button_size)} />
            </motion.span>
          )}
        </AnimatePresence>
        {appearance.button_style === 'icon-text' && (
          <span class="wpwc-btn-text">{appearance.button_text}</span>
        )}
        {everyoneOffline && <span class="wpwc-btn-status" aria-hidden="true" />}
      </button>

      {/* Tooltip — AnimatePresence for smooth enter/exit */}
      <AnimatePresence>
        {appearance.tooltip_text && isVisible && !isOpen && showTooltip && (
          <motion.div
            class="wpwc-tooltip"
            role="tooltip"
            initial={{ opacity: 0, x: isRight ? 6 : -6 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{    opacity: 0, x: isRight ? 6 : -6 }}
            transition={{ duration: 0.16, ease: 'easeOut' }}
          >
            {appearance.tooltip_text}
          </motion.div>
        )}
      </AnimatePresence>

      {/* Contacts panel — AnimatePresence for mount/unmount animation */}
      <AnimatePresence>
        {isOpen && (
          <motion.div
            class="wpwc-panel"
            data-corner={corner}
            role="dialog"
            aria-label={appearance.aria_panel}
            initial={{ opacity: 0, scale: 0.88, y: isBottom ? 12 : -12 }}
            animate={{ opacity: 1, scale: 1,    y: 0 }}
            exit={{    opacity: 0, scale: 0.88, y: isBottom ? 12 : -12 }}
            transition={{
              type: 'spring',
              stiffness: 420,
              damping: 30,
              mass: 0.75,
            }}
            style={{ transformOrigin: panelOrigin }}
          >
            <div class="wpwc-panel-inner">
              {/* Panel header */}
              <div
                class="wpwc-panel-header"
                style={{ background: appearance.color_bg, color: appearance.color_fg }}
              >
                <span class="wpwc-panel-header-icon">
                  <WaIcon size={20} />
                </span>
                <span class="wpwc-panel-header-text">
                  <strong>{appearance.panel_title}</strong>
                  <span>{everyoneOffline && appearance.offline_subtitle ? appearance.offline_subtitle : appearance.panel_subtitle}</span>
                </span>
              </div>

              {/* Contact list */}
              <div class="wpwc-panel-body">
                {entries.length > 0
                  ? entries.map(({ item, status }, i) => (
                      <ContactItem
                        key={item.id}
                        {...item}
                        index={i}
                        status={status}
                        statusText={formatStatus(item.availability ?? ALWAYS_ONLINE, status, statusTexts, weekdays)}
                      />
                    ))
                  : <p class="wpwc-panel-empty">{appearance.empty_text}</p>
                }
              </div>

              {/* Footer */}
              {appearance.footer_text && (
                <div class="wpwc-panel-footer">
                  <WaIcon size={12} />
                  <span>{appearance.footer_text}</span>
                </div>
              )}
            </div>{/* /.wpwc-panel-inner */}
          </motion.div>
        )}
      </AnimatePresence>

    </motion.div>
  );
}

