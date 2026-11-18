/**
 * Minimal local types for masonry-layout v4.2.2, which ships none.
 *
 * The package builds its class with `Outlayer.create('masonry')`, so the
 * constructor and most of the public surface live in outlayer.js rather than in
 * masonry.js and cannot be inferred from the module's own shape. These
 * declarations cover what this site uses plus the documented options; Outlayer's
 * underscore-prefixed internals are deliberately left out.
 *
 * The alternative is `npm i -D @types/masonry-layout`, which pulls in transitive
 * @types packages for outlayer, get-size and ev-emitter for marginally wider
 * coverage. A local file keeps the surface honest about what we actually call
 * and costs nothing to install.
 */
declare module 'masonry-layout' {
    /** Column width or gutter: a pixel count, a selector, or an element to measure. */
    type Measurement = number | string | Element;

    interface MasonryOptions {
        /** Selector for the items to lay out. Required in practice. */
        itemSelector?: string;
        columnWidth?: Measurement;
        gutter?: Measurement;
        /** Size columns and positions in percentages rather than pixels. */
        percentPosition?: boolean;
        /** Place items in reading order instead of filling the shortest column. */
        horizontalOrder?: boolean;
        /** Fit the container's width to the columns actually used. */
        fitWidth?: boolean;
        /** Delay between item transitions, ms. */
        stagger?: number | string;
        transitionDuration?: number | string;
        originLeft?: boolean;
        originTop?: boolean;
        /** Re-layout when the window resizes. */
        resize?: boolean;
        resizeContainer?: boolean;
        /** Lay out on construction. */
        initLayout?: boolean;
        containerStyle?: Record<string, string | number>;
        hiddenStyle?: Record<string, string | number>;
        visibleStyle?: Record<string, string | number>;
        itemOptions?: Record<string, unknown>;
    }

    class Masonry {
        constructor(element: Element | string, options?: MasonryOptions);

        static defaults: MasonryOptions;
        static namespace: string;
        /** The instance bound to an element, if any. */
        static data(element: Element | string): Masonry | undefined;

        element: Element;
        options: MasonryOptions;
        items: unknown[];

        layout(items?: Element[] | unknown[], isInstant?: boolean): void;
        reloadItems(): void;
        resize(): void;
        destroy(): void;
        /** True when the container's width changed, meaning a re-layout is due. */
        needsResizeLayout(): boolean;
        bindResize(): void;
        unbindResize(): void;

        on(event: string, listener: (...args: unknown[]) => void): this;
        off(event: string, listener?: (...args: unknown[]) => void): this;
    }

    export default Masonry;
}
