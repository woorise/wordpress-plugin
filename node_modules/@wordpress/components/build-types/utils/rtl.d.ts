/**
 * A higher-order function that create an incredibly basic ltr -> rtl style converter for CSS objects.
 *
 * @param {import('react').CSSProperties} ltrStyles   Ltr styles. Converts and renders from ltr -> rtl styles, if applicable.
 * @param {import('react').CSSProperties} [rtlStyles] Rtl styles. Renders if provided.
 *
 * @return {Function} A function to output CSS styles for Emotion's renderer
 */
export function rtl(ltrStyles?: import('react').CSSProperties, rtlStyles?: import("react").CSSProperties | undefined): Function;
export function convertLTRToRTL(ltrStyles?: import('react').CSSProperties): import('react').CSSProperties;
//# sourceMappingURL=rtl.d.ts.map