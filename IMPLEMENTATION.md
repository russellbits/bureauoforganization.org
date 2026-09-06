# Implementation Plan: Tag-based Routing for Featured Sites

This document describes how to implement tag-based URLs for the Bureau of Organization website.

## Problem Statement

Currently, featured sites (stored in `src/content/featured-sites/*.yaml`) have tags but there's no way to view sites by tag. We need URLs like `bureauoforganization.org/tags/[tagname]` that list all featured sites with that tag.

## Solution: Dynamic Routing with Static Generation

We'll create a dynamic route that leverages Astro's content collections and static path generation.

### Implementation Steps:

1. **Create a dynamic route file** at `src/pages/tags/[tag].astro` that:
   - Accepts a tag parameter from the URL
   - Fetches all featured sites
   - Filters sites that have the specified tag
   - Displays them in a grid layout

2. **Implement proper `getStaticPaths()` function** to pre-generate all tag-specific pages

3. **Design the page layout** to properly display tag results

## File to Create: `src/pages/tags/[tag].astro`

```astro
---
import { getCollection } from 'astro:content';
import Layout from '@layouts/Layout.astro';

// Get the tag from the URL parameter
const tag = Astro.params.tag;

// Fetch all featured sites
const featuredSites = await getCollection('featured_sites');

// Filter sites by the tag
const sitesWithTag = featuredSites.filter(site => 
  site.data.tags && site.data.tags.includes(tag)
);

// Sort sites by label for consistent display
const sortedSites = sitesWithTag.sort((a, b) => 
  a.data.label.localeCompare(b.data.label)
);

// Generate static paths for all available tags
export async function getStaticPaths() {
  // Get all unique tags from all featured sites
  const allTags = new Set();
  const allSites = await getCollection('featured_sites');
  
  allSites.forEach(site => {
    if (site.data.tags) {
      site.data.tags.forEach(tag => allTags.add(tag));
    }
  });
  
  // Create a path for each tag
  return Array.from(allTags).map(tag => ({
    params: { tag },
    props: { tag }
  }));
}
---

<Layout>
  <main>
    <h1>Featured Sites with Tag: {tag}</h1>
    
    {sortedSites.length > 0 ? (
      <div class="tagged-sites-grid">
        {sortedSites.map(site => (
          <div class="tagged-site-card" key={site.slug}>
            <div class="featured-site-card">
              <img src={`/web-site-previews/${site.slug}.jpg`} width="600" />
              <h3><a href={site.data.url}>{site.data.label}</a></h3>
              <p>{site.data.description}</p>
              <div class="tags">
                {site.data.tags.map(tag => <span class="tag">{tag}</span>)}
              </div>
            </div>
          </div>
        ))}
      </div>
    ) : (
      <p>No featured sites found with the tag: {tag}</p>
    )}
  </main>
</Layout>

<style>
.tagged-sites-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 20px;
  margin-top: 20px;
}

.tag:not(:last-child)::after {
  content: ", ";
}

.featured-site-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.featured-site-card img {
  max-width: 200px;
  border: 3px solid purple;
}
</style>
```

## Key Design Decisions

1. **Static Path Generation**: By using `getStaticPaths()`, we generate all possible tag pages at build time, which is compatible with static hosting

2. **Direct Rendering**: Instead of reusing `FeaturedWebSite.astro`, we directly render the content to avoid issues with the component expecting a slug parameter

3. **Responsive Grid**: Uses CSS Grid to efficiently display the sites in a flexible layout

4. **Consistent Styling**: Maintains the site's existing aesthetic with the card border styling

## Testing

After implementation, the following URLs should work:
- `http://localhost:22118/tags/todo` (if there are sites with "todo" tag)  
- `http://localhost:22118/tags/game` (if there are sites with "game" tag)
- `http://localhost:22118/tags/nonexistent` (should show empty state)

## Notes

- The solution uses Astro's content collections API to efficiently filter and sort sites
- All tag pages are generated at build time, making them highly performant
- Preview images are loaded based on the site slug (e.g., `eggman.jpg`)
- This approach is compatible with static builds and doesn't require server-side rendering