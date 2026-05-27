import 'vue';

// Global translation helpers registered in app.ts, usable in any template as $t / $tChoice.
declare module 'vue' {
    interface ComponentCustomProperties {
        $t: (key: string, replace?: Record<string, string | number>) => string;
        $tChoice: (key: string, count: number, replace?: Record<string, string | number>) => string;
    }
}
