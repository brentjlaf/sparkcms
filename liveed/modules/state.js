// File: state.js
const stateMap = new Map();
let nextId = 1;

export function ensureBlockState(block) {
  if (!block) return null;
  if (!block.dataset.blockId) {
    block.dataset.blockId = 'b' + nextId++;
  }
  const id = block.dataset.blockId;
  if (!stateMap.has(id)) {
    const data = {};
    const reserved = ['blockId', 'template', 'original', 'active', 'ts'];
    Object.keys(block.dataset).forEach((k) => {
      if (!reserved.includes(k)) {
        data[k] = block.dataset[k];
        delete block.dataset[k];
      }
    });
    stateMap.set(id, data);
  }
  return id;
}

export function getSettings(block) {
  ensureBlockState(block);
  return stateMap.get(block.dataset.blockId);
}

export function setSetting(block, name, value) {
  const s = getSettings(block);
  s[name] = value;
}

export function getSetting(block, name, defaultValue = '') {
  const s = getSettings(block);
  return s[name] !== undefined ? s[name] : defaultValue;
}

export { stateMap };
