import { motion, useReducedMotion } from 'framer-motion';
import { usePage } from '@inertiajs/react';

/**
 * Wraps page content in a smooth fade/slide transition on navigation.
 * Respects prefers-reduced-motion (renders without animation).
 */
export default function PageTransition({ children }: { children: React.ReactNode }) {
    const reduce = useReducedMotion();
    const { url } = usePage();

    if (reduce) return <>{children}</>;

    return (
        <motion.div
            key={url}
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.25, ease: 'easeOut' }}
        >
            {children}
        </motion.div>
    );
}
