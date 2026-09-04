// src/content.config.ts
import { defineCollection, z } from 'astro:content';
import { file, glob } from 'astro/loaders';

// --- Existing Collections ---

const header_images_links = defineCollection({
  loader: file("./src/data/header-image-links.yaml"),
  schema: z.object({
    id: z.string(),
    label: z.string(),
    description: z.string(),
    url: z.string().url(),
  }),
});

const top_nav_links = defineCollection({
  loader: file("./src/data/top-nav.yaml"),
  schema: z.object({
    id: z.string(),
    label: z.string(),
    description: z.string().optional(),
    url: z.string().url(),
    tags: z.array(z.string()).optional(),
  }),
});

const link_schema = z.object({
  label: z.string(),
  desc: z.string().optional(),
  url: z.string().url(),
  tags: z.array(z.string()).optional(),
});

const categories = defineCollection({
  loader: file("./src/data/links.yaml"),
  schema: z.object({
    id: z.string(),
    name: z.string(),
    url: z.string().optional(),
    links: z.array(link_schema),
  }),
});

const featured_sites = defineCollection({
  loader: glob({ pattern: '**/[^_]*.yaml', base: './src/content/featured-sites' }),
  schema: z.object({
    label: z.string(),
    description: z.string(),
    url: z.string().url(),
    tags: z.array(z.string()),
  }),
});

// --- Export All Collections ---

export const collections = {
  header_images_links,
  top_nav_links,
  categories,
  featured_sites
};
