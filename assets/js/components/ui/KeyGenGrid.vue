<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { __ } from '../../lib/helpers.js';

const COLS = 38;
const ROWS = 10;
const TOTAL_CELLS = COLS * ROWS;

const gridRef = ref(null);
let animTimer = null;
let lifeStepTimer = 0;

let lifeGrid = new Uint8Array(TOTAL_CELLS);
let nextLifeGrid = new Uint8Array(TOTAL_CELLS);
const trailGrid = new Float32Array(TOTAL_CELLS);
const currentLevels = new Uint8Array(TOTAL_CELLS);

let packets = [];

const randomRange = (min, max) => min + Math.random() * (max - min);

const initPackets = () => {
  packets = [
    // Left cluster sector (X: 4 to 11, Y: 2 to 7)
    {
      x: randomRange(4.5, 10.5),
      y: randomRange(2.0, 6.5),
      vx: (Math.random() > 0.5 ? 1 : -1) * randomRange(0.12, 0.20),
      vy: (Math.random() > 0.5 ? 1 : -1) * randomRange(0.08, 0.16),
      sigma: randomRange(1.6, 2.0),
      phase: randomRange(0, Math.PI * 2),
      freq: randomRange(0.04, 0.08)
    },
    // Center cluster sector (X: 15 to 22, Y: 2 to 7)
    {
      x: randomRange(16.0, 22.0),
      y: randomRange(2.0, 6.5),
      vx: (Math.random() > 0.5 ? 1 : -1) * randomRange(0.10, 0.18),
      vy: (Math.random() > 0.5 ? 1 : -1) * randomRange(0.08, 0.16),
      sigma: randomRange(1.8, 2.2),
      phase: randomRange(0, Math.PI * 2),
      freq: randomRange(0.04, 0.08)
    },
    // Right cluster sector (X: 27 to 34, Y: 2 to 7)
    {
      x: randomRange(27.0, 33.5),
      y: randomRange(2.0, 6.5),
      vx: (Math.random() > 0.5 ? 1 : -1) * randomRange(0.12, 0.20),
      vy: (Math.random() > 0.5 ? 1 : -1) * randomRange(0.08, 0.16),
      sigma: randomRange(1.6, 2.0),
      phase: randomRange(0, Math.PI * 2),
      freq: randomRange(0.04, 0.08)
    }
  ];
};

const setLife = (x, y, val = 1) => {
  if (x >= 0 && x < COLS && y >= 0 && y < ROWS) {
    lifeGrid[y * COLS + x] = val;
  }
};

const seedInitialPatterns = () => {
  lifeGrid.fill(0);
  trailGrid.fill(0);

  packets.forEach((p) => {
    const cx = Math.round(p.x);
    const cy = Math.round(p.y);

    let spawned = 0;
    for (let dy = -2; dy <= 2; dy++) {
      for (let dx = -3; dx <= 3; dx++) {
        const distSq = dx * dx + dy * dy;
        const prob = Math.exp(-distSq / 3.2) * 0.88;
        if (Math.random() < prob) {
          setLife((cx + dx + COLS) % COLS, Math.max(0, Math.min(ROWS - 1, cy + dy)));
          spawned++;
        }
      }
    }

    if (spawned < 5) {
      setLife(cx, cy);
      setLife((cx + 1) % COLS, cy);
      setLife(cx, Math.max(0, Math.min(ROWS - 1, cy + 1)));
      setLife((cx + 1) % COLS, Math.max(0, Math.min(ROWS - 1, cy + 1)));
      setLife((cx - 1 + COLS) % COLS, cy);
    }
  });
};

const countNeighbors = (x, y) => {
  let count = 0;
  for (let dy = -1; dy <= 1; dy++) {
    const ny = y + dy;
    if (ny < 0 || ny >= ROWS) continue;
    for (let dx = -1; dx <= 1; dx++) {
      if (dx === 0 && dy === 0) continue;
      const nx = (x + dx + COLS) % COLS;
      if (lifeGrid[ny * COLS + nx]) {
        count++;
      }
    }
  }
  return count;
};

const GLIDER_PATTERNS = [
  [[1, 0], [2, 1], [0, 2], [1, 2], [2, 2]], // South-East
  [[1, 0], [0, 1], [0, 2], [1, 2], [2, 2]], // South-West
  [[0, 0], [1, 0], [2, 0], [0, 1], [1, 2]], // North-East
  [[0, 0], [1, 0], [2, 0], [2, 1], [1, 2]]  // North-West
];

const SPARK_PATTERNS = [
  [[0, 0], [1, 0], [0, 1]], // L-tromino
  [[1, 0], [0, 1], [1, 1], [2, 1]], // T-tetromino
  [[0, 1], [1, 0], [1, 1], [2, 1], [2, 2]] // R-pentomino
];

const spawnGlider = (x, y, dir = Math.floor(Math.random() * GLIDER_PATTERNS.length)) => {
  const pts = GLIDER_PATTERNS[dir];
  pts.forEach(([dx, dy]) => {
    const nx = (x + dx + COLS) % COLS;
    const ny = Math.max(0, Math.min(ROWS - 1, y + dy));
    setLife(nx, ny);
  });
};

const spawnSpark = (x, y) => {
  const pts = SPARK_PATTERNS[Math.floor(Math.random() * SPARK_PATTERNS.length)];
  pts.forEach(([dx, dy]) => {
    const nx = (x + dx + COLS) % COLS;
    const ny = Math.max(0, Math.min(ROWS - 1, y + dy));
    setLife(nx, ny);
  });
};

let ripples = [];

const addRipple = (x, y) => {
  if (ripples.length < 5) {
    ripples.push({
      x,
      y,
      radius: 0.3,
      maxRadius: 3.8,
      speed: 0.16,
      amp: 0.55,
      decay: 0.022
    });
  }
};

let spontaneousTimer = 0;

const stepLife = () => {
  nextLifeGrid.fill(0);
  let liveCount = 0;

  for (let y = 0; y < ROWS; y++) {
    for (let x = 0; x < COLS; x++) {
      const idx = y * COLS + x;
      const neighbors = countNeighbors(x, y);
      const isAlive = lifeGrid[idx];

      if (isAlive) {
        if (neighbors === 2 || neighbors === 3) {
          nextLifeGrid[idx] = 1;
          liveCount++;
        }
      } else {
        if (neighbors === 3) {
          nextLifeGrid[idx] = 1;
          liveCount++;
        }
      }
    }
  }

  spontaneousTimer++;
  if (spontaneousTimer >= 7) {
    spontaneousTimer = 0;
    const rx = Math.floor(Math.random() * COLS);
    const ry = Math.floor(Math.random() * (ROWS - 4)) + 2;

    if (Math.random() > 0.45) {
      spawnGlider(rx, ry);
    } else {
      spawnSpark(rx, ry);
    }
    addRipple(rx, ry);
  }

  if (liveCount < 20) {
    packets.forEach((p) => {
      const px = Math.round(p.x);
      const py = Math.round(p.y);
      setLife((px + 1) % COLS, py, 1);
      setLife(px, (py + 1) % ROWS, 1);
      setLife((px + 1) % COLS, (py + 1) % ROWS, 1);
    });

    const rx = Math.floor(Math.random() * COLS);
    const ry = Math.floor(Math.random() * (ROWS - 4)) + 2;
    spawnGlider(rx, ry);
    addRipple(rx, ry);
  }

  const temp = lifeGrid;
  lifeGrid = nextLifeGrid;
  nextLifeGrid = temp;
};

const updateSimulation = (cellNodes) => {
  if (!cellNodes || cellNodes.length < TOTAL_CELLS) return;

  packets.forEach((p) => {
    p.x = (p.x + p.vx + COLS) % COLS;
    p.y += p.vy;
    if (p.y <= 1.5 || p.y >= ROWS - 1.5) {
      p.vy = -p.vy;
      p.y = Math.max(1.5, Math.min(ROWS - 1.5, p.y));
    }
    p.phase += p.freq;
  });

  for (let i = ripples.length - 1; i >= 0; i--) {
    const r = ripples[i];
    r.radius += r.speed;
    r.amp -= r.decay;
    if (r.amp <= 0.04 || r.radius >= r.maxRadius) {
      ripples.splice(i, 1);
    }
  }

  lifeStepTimer++;
  if (lifeStepTimer >= 4) {
    lifeStepTimer = 0;
    stepLife();
  }

  for (let y = 0; y < ROWS; y++) {
    for (let x = 0; x < COLS; x++) {
      const idx = y * COLS + x;

      let gSum = 0;
      for (let k = 0; k < packets.length; k++) {
        const p = packets[k];
        let dx = Math.abs(x - p.x);
        if (dx > COLS / 2) dx = COLS - dx;
        const dy = Math.abs(y - p.y);

        const distSq = dx * dx + dy * dy;
        const amplitude = 0.8 + 0.25 * Math.sin(p.phase);
        gSum += amplitude * Math.exp(-distSq / (2 * p.sigma * p.sigma));
      }

      for (let rIdx = 0; rIdx < ripples.length; rIdx++) {
        const r = ripples[rIdx];
        let rdx = Math.abs(x - r.x);
        if (rdx > COLS / 2) rdx = COLS - rdx;
        const rdy = Math.abs(y - r.y);
        const dist = Math.sqrt(rdx * rdx + rdy * rdy);
        const ringDist = Math.abs(dist - r.radius);
        gSum += r.amp * Math.exp(-(ringDist * ringDist) / 0.7);
      }

      if (lifeGrid[idx]) {
        trailGrid[idx] = 1.0;
      } else {
        trailGrid[idx] = Math.max(0, trailGrid[idx] * 0.88);
      }

      const intensity = Math.min(
        1,
        gSum * 0.55 + lifeGrid[idx] * 0.35 + trailGrid[idx] * 0.25
      );

      let lvl = 0;
      if (intensity >= 0.72) {
        lvl = 4;
      } else if (intensity >= 0.48) {
        lvl = 3;
      } else if (intensity >= 0.26) {
        lvl = 2;
      } else if (intensity >= 0.08) {
        lvl = 1;
      }

      if (currentLevels[idx] !== lvl) {
        currentLevels[idx] = lvl;
        const el = cellNodes[idx];
        if (el) {
          el.className = lvl > 0 ? `keygen-cell level-${lvl}` : 'keygen-cell';
        }
      }
    }
  }
};

onMounted(() => {
  initPackets();
  seedInitialPatterns();

  // Seed
  const rx = Math.floor(Math.random() * COLS);
  const ry = Math.floor(Math.random() * (ROWS - 4)) + 2;
  spawnGlider(rx, ry);
  addRipple(rx, ry);

  const cellNodes = gridRef.value ? gridRef.value.children : [];

  updateSimulation(cellNodes);

  // Run at ~30 FPS (33ms) for smooth continuous wave animation
  animTimer = setInterval(() => {
    updateSimulation(cellNodes);
  }, 33);
});

onUnmounted(() => {
  if (animTimer) {
    clearInterval(animTimer);
    animTimer = null;
  }
  ripples = [];
});
</script>

<template>
  <div
    ref="gridRef"
    class="keygen-grid"
    role="status"
    :aria-label="__('Generating cryptographic keys...')"
  >
    <div
      v-for="i in TOTAL_CELLS"
      :key="i"
      class="keygen-cell"
    />
  </div>
</template>
