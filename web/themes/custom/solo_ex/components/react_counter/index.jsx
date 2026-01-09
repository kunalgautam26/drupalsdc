import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
// Vite will automatically compile this and inject it.
import './react_counter.scss';

// 1. The Actual React Component
const CounterApp = ({ startCount }) => {
  const [count, setCount] = useState(startCount);

  return (
    <div className="p-4 border rounded">
      <h3>React Counter</h3>
      <p>Count: {count}</p>
      <button onClick={() => setCount(count + 1)}>Increment</button>
    </div>
  );
};

// 2. The "Glue" Code (Replaces jQuery $(document).ready)
// We look for all instances of our Twig mount point
const mountPoints = document.querySelectorAll('[data-react-counter-app]');
console.log('mountPoints', mountPoints);

mountPoints.forEach((domNode) => {
  // Check if already processed (optional, similar to jQuery.once)
  if (domNode.dataset.processed) return;
  domNode.dataset.processed = "true";

  // Read props passed from Twig via data attributes
  const startCount = parseInt(domNode.dataset.startCount, 10);

  // Mount React
  const root = createRoot(domNode);
  root.render(<CounterApp startCount={startCount} />);
});