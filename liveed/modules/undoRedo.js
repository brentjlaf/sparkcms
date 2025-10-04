// File: undoRedo.js
import { serializeCanvas, renderCanvasFromSchema } from './state.js';

const EMPTY_SCHEMA = { version: 1, blocks: [] };

function cloneSchema(schema) {
  if (!schema || typeof schema !== 'object') return { version: 1, blocks: [] };
  try {
    return JSON.parse(JSON.stringify(schema));
  } catch (e) {
    return { version: 1, blocks: [] };
  }
}

function cloneBlock(block) {
  if (!block || typeof block !== 'object') return {};
  try {
    return JSON.parse(JSON.stringify(block));
  } catch (e) {
    return {};
  }
}

function isBlockWrapper(node) {
  return !!node && node.classList && node.classList.contains('block-wrapper');
}

function getDropAreas(block) {
  if (!block) return [];
  return Array.from(block.querySelectorAll('.drop-area')).filter(
    (area) => area.closest('.block-wrapper') === block
  );
}

export function getBlockPath(block, root) {
  if (!block || !root || !root.contains(block)) return null;
  const path = [];
  let current = block;
  while (current && current !== root) {
    const parent = current.parentElement;
    if (!parent) break;
    const siblings = Array.from(parent.children).filter(isBlockWrapper);
    const index = siblings.indexOf(current);
    if (index < 0) break;
    path.unshift(index);
    const parentBlock = parent.closest('.block-wrapper');
    if (!parentBlock || parentBlock === current) {
      break;
    }
    const areas = getDropAreas(parentBlock);
    const areaIndex = areas.indexOf(parent);
    if (areaIndex < 0) break;
    path.unshift(areaIndex);
    current = parentBlock;
  }
  return path;
}

export function getPathLocation(path) {
  if (!Array.isArray(path) || path.length === 0) {
    return { path: [], parentPath: null, areaIndex: null, index: 0 };
  }
  const normalized = path.slice();
  const index = normalized[normalized.length - 1];
  const areaIndex = normalized.length > 1 ? normalized[normalized.length - 2] : null;
  const parentPath = normalized.length > 2 ? normalized.slice(0, normalized.length - 2) : normalized.length > 1 ? normalized.slice(0, 1) : null;
  return { path: normalized, parentPath, areaIndex, index };
}

function getBlockByPath(schema, path) {
  if (!schema || !Array.isArray(path) || path.length === 0) return null;
  let blocks = schema.blocks;
  let block = null;
  for (let i = 0; i < path.length; i++) {
    const value = path[i];
    if (i % 2 === 0) {
      if (!Array.isArray(blocks) || value < 0 || value >= blocks.length) return null;
      block = blocks[value];
    } else {
      if (!block) return null;
      if (!Array.isArray(block.areas)) block.areas = [];
      if (!Array.isArray(block.areas[value])) block.areas[value] = [];
      blocks = block.areas[value];
    }
  }
  return block || null;
}

function resolveBlockLocation(schema, path) {
  if (!schema || !Array.isArray(path) || path.length === 0) return null;
  let blocks = schema.blocks;
  let i = 0;
  while (i < path.length - 1) {
    const blockIndex = path[i];
    if (!Array.isArray(blocks) || blockIndex < 0 || blockIndex >= blocks.length) return null;
    const block = blocks[blockIndex];
    const areaIndex = path[i + 1];
    if (!block) return null;
    if (!Array.isArray(block.areas)) block.areas = [];
    if (!Array.isArray(block.areas[areaIndex])) block.areas[areaIndex] = [];
    blocks = block.areas[areaIndex];
    i += 2;
  }
  const index = path[path.length - 1];
  if (!Array.isArray(blocks)) return null;
  return { blocks, index };
}

function getArea(schema, parentPath, areaIndex) {
  if (!schema) return null;
  if (!Array.isArray(parentPath) || parentPath.length === 0 || parentPath == null) {
    return schema.blocks;
  }
  const parentBlock = getBlockByPath(schema, parentPath);
  if (!parentBlock) return null;
  if (!Array.isArray(parentBlock.areas)) parentBlock.areas = [];
  if (typeof areaIndex !== 'number' || areaIndex < 0) return null;
  if (!Array.isArray(parentBlock.areas[areaIndex])) parentBlock.areas[areaIndex] = [];
  return parentBlock.areas[areaIndex];
}

function applyAction(schema, action) {
  if (!schema || !action || typeof action !== 'object') return schema;
  switch (action.type) {
    case 'insert': {
      const parentPath = Array.isArray(action.parentPath) ? action.parentPath.slice() : null;
      const areaIndex = typeof action.areaIndex === 'number' ? action.areaIndex : null;
      const index = typeof action.index === 'number' ? action.index : 0;
      const area = getArea(schema, parentPath, areaIndex);
      if (!Array.isArray(area)) return schema;
      const insertIndex = Math.max(0, Math.min(index, area.length));
      area.splice(insertIndex, 0, cloneBlock(action.block));
      return schema;
    }
    case 'delete': {
      const location = resolveBlockLocation(schema, action.path);
      if (!location) return schema;
      if (location.index < 0 || location.index >= location.blocks.length) return schema;
      location.blocks.splice(location.index, 1);
      return schema;
    }
    case 'move': {
      if (!Array.isArray(action.fromPath) || !Array.isArray(action.toPath)) return schema;
      const source = resolveBlockLocation(schema, action.fromPath);
      if (!source) return schema;
      if (source.index < 0 || source.index >= source.blocks.length) return schema;
      const [moved] = source.blocks.splice(source.index, 1);
      if (!moved) return schema;
      const { parentPath, areaIndex, index } = getPathLocation(action.toPath);
      const targetArea = getArea(schema, parentPath, areaIndex);
      if (!Array.isArray(targetArea)) return schema;
      let insertIndex = typeof index === 'number' ? index : targetArea.length;
      if (source.blocks === targetArea && source.index < insertIndex) {
        insertIndex -= 1;
      }
      insertIndex = Math.max(0, Math.min(insertIndex, targetArea.length));
      targetArea.splice(insertIndex, 0, moved);
      return schema;
    }
    case 'replace': {
      const location = resolveBlockLocation(schema, action.path);
      if (!location) return schema;
      if (location.index < 0 || location.index >= location.blocks.length) return schema;
      location.blocks[location.index] = cloneBlock(action.block);
      return schema;
    }
    default:
      return schema;
  }
}

function applyOperations(baseSchema, operations, count) {
  const schema = cloneSchema(baseSchema);
  const total = Math.min(typeof count === 'number' ? count : operations.length, operations.length);
  for (let i = 0; i < total; i++) {
    applyAction(schema, operations[i]);
  }
  return schema;
}

function normalizeOperation(action) {
  if (!action || typeof action !== 'object') return null;
  switch (action.type) {
    case 'insert':
      if (!action.block) return null;
      return {
        type: 'insert',
        parentPath: Array.isArray(action.parentPath) ? action.parentPath.slice() : null,
        areaIndex: typeof action.areaIndex === 'number' ? action.areaIndex : null,
        index: typeof action.index === 'number' ? action.index : 0,
        block: cloneBlock(action.block),
      };
    case 'delete':
      if (!Array.isArray(action.path)) return null;
      return {
        type: 'delete',
        path: action.path.slice(),
      };
    case 'move':
      if (!Array.isArray(action.fromPath) || !Array.isArray(action.toPath)) return null;
      return {
        type: 'move',
        fromPath: action.fromPath.slice(),
        toPath: action.toPath.slice(),
      };
    case 'replace':
      if (!Array.isArray(action.path) || !action.block) return null;
      return {
        type: 'replace',
        path: action.path.slice(),
        block: cloneBlock(action.block),
      };
    default:
      return null;
  }
}

export function initUndoRedo(options = {}) {
  const canvas = options.canvas;
  const rendererOptions = options.rendererOptions || {};
  const onChange = typeof options.onChange === 'function' ? options.onChange : null;
  const maxHistory = Number.isInteger(options.maxHistory) ? options.maxHistory : 15;

  if (!canvas) {
    return {
      recordOperation: () => {},
      undo: () => {},
      redo: () => {},
      resetFromSchema: () => {},
      resetFromCanvas: () => {},
    };
  }

  let baseSchema = cloneSchema(serializeCanvas(canvas));
  let operations = [];
  let pointer = 0;
  let applying = false;

  const rebuild = async () => {
    const targetSchema = applyOperations(baseSchema, operations, pointer);
    applying = true;
    try {
      await renderCanvasFromSchema(canvas, targetSchema, rendererOptions);
    } finally {
      applying = false;
    }
    if (onChange) onChange(targetSchema);
    document.dispatchEvent(new Event('canvasUpdated'));
    return targetSchema;
  };

  const recordOperation = (action) => {
    if (applying) return;
    const op = normalizeOperation(action);
    if (!op) return;
    if (pointer < operations.length) {
      operations = operations.slice(0, pointer);
    }
    operations.push(op);
    pointer = operations.length;
    if (operations.length > maxHistory) {
      const removed = operations.shift();
      pointer = Math.max(pointer - 1, 0);
      baseSchema = applyOperations(baseSchema, [removed], 1);
    }
  };

  const undo = () => {
    if (applying || pointer === 0) return Promise.resolve();
    pointer -= 1;
    return rebuild();
  };

  const redo = () => {
    if (applying || pointer >= operations.length) return Promise.resolve();
    pointer += 1;
    return rebuild();
  };

  const resetFromSchema = (schema) => {
    baseSchema = cloneSchema(schema || EMPTY_SCHEMA);
    operations = [];
    pointer = 0;
  };

  const resetFromCanvas = () => {
    resetFromSchema(serializeCanvas(canvas));
  };

  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key === 'z') {
      e.preventDefault();
      undo();
    } else if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.shiftKey && e.key === 'Z'))) {
      e.preventDefault();
      redo();
    }
  });

  return { recordOperation, undo, redo, resetFromSchema, resetFromCanvas };
}
