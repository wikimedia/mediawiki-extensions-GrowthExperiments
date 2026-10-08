/*
 * The types of the Codex that InterestSelector.vue requires.
 *
 * ResourceLoader writes "codex.js" at the root of a module that tree-shakes
 * Codex, so the file is not on disk and TypeScript cannot read it. This
 * declaration gives it the contents of the library instead. It declares types
 * only: ResourceLoader does not package it, and Jest supplies the same module
 * with a virtual mock.
 */
export * from '@wikimedia/codex';
