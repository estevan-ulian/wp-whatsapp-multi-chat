export interface Account {
  id: number;
  name: string;
  number: string;
  predefined_message: string;
  tagline: string;
  thumbnail: string;
}

export interface AppearanceSettings {
  position_corner: 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right';
  offset_x: number;
  offset_y: number;
  button_style: 'icon-only' | 'icon-text';
  button_text: string;
  button_size: 'small' | 'medium' | 'large';
  color_bg: string;
  color_fg: string;
  animation: 'none' | 'pulse' | 'bounce';
  tooltip_text: string;
  appear_delay: number;
  hide_mobile: 0 | 1;
  hide_desktop: 0 | 1;
  panel_title: string;
  panel_subtitle: string;
  cta_text: string;
  empty_text: string;
  footer_text: string;
  aria_open: string;
  aria_panel: string;
}

export interface AppParams {
  root: string;
  baseUrl: string;
  accounts: Account[];
  appearance: AppearanceSettings;
}

declare global {
  interface Window {
    wpWhatsAppMultiChat: AppParams
  }
}

const params = window.wpWhatsAppMultiChat;

const rootId = params?.root || "app";

const root = document.getElementById(rootId) as HTMLElement;
const baseUrl = params?.baseUrl || "";
const accounts = params?.accounts ?? [];
const appearance = params?.appearance;

export const appConfig = Object.freeze({
  root,
  baseUrl,
  accounts,
  appearance,
});