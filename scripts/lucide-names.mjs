// Prints every icon component name lucide-react exports, one per line.
import process from 'node:process';

const icons = await import('lucide-react');

process.stdout.write(Object.keys(icons).sort().join('\n') + '\n');
