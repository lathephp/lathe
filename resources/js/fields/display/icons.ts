import {
  ArrowDown,
  ArrowUp,
  Ban,
  Bell,
  Check,
  Circle,
  CircleAlert,
  CircleCheck,
  CircleX,
  Clock,
  Eye,
  Flag,
  Heart,
  Info,
  LoaderCircle,
  Lock,
  Mail,
  Package,
  Star,
  TrendingDown,
  TrendingUp,
  TriangleAlert,
  Truck,
  X,
  type LucideIcon,
} from 'lucide-react';

// Only the icons named here ship; the full Lucide set would weigh several hundred KB.
// check-circle, alert-circle and alert-triangle are the old Lucide names the Icon enum still holds.
const icons: Record<string, LucideIcon | undefined> = {
  'arrow-down': ArrowDown,
  'arrow-up': ArrowUp,
  ban: Ban,
  bell: Bell,
  check: Check,
  circle: Circle,
  'circle-alert': CircleAlert,
  'circle-check': CircleCheck,
  'circle-x': CircleX,
  clock: Clock,
  eye: Eye,
  flag: Flag,
  heart: Heart,
  info: Info,
  'loader-circle': LoaderCircle,
  lock: Lock,
  mail: Mail,
  package: Package,
  star: Star,
  'trending-down': TrendingDown,
  'trending-up': TrendingUp,
  'triangle-alert': TriangleAlert,
  truck: Truck,
  x: X,
  'check-circle': CircleCheck,
  'alert-circle': CircleAlert,
  'alert-triangle': TriangleAlert,
};

/** A shipped icon by its Lucide kebab-case name, or a plain circle for a name the panel does not ship. */
export function iconNamed(name: string): LucideIcon {
  return icons[name] ?? Circle;
}
