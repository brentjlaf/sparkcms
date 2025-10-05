// File: base64.js
function toBinaryString(value) {
  if (typeof value !== 'string') {
    value = value != null ? String(value) : '';
  }
  if (!value) return '';
  if (typeof globalThis.TextEncoder === 'function') {
    const encoder = new globalThis.TextEncoder();
    const bytes = encoder.encode(value);
    let binary = '';
    bytes.forEach((byte) => {
      binary += String.fromCharCode(byte);
    });
    return binary;
  }
  if (typeof encodeURIComponent === 'function' && typeof unescape === 'function') {
    return unescape(encodeURIComponent(value));
  }
  let binary = '';
  for (let i = 0; i < value.length; i += 1) {
    const code = value.charCodeAt(i);
    binary += String.fromCharCode(code & 0xff);
  }
  return binary;
}

function fromBinaryString(binary) {
  if (!binary) return '';
  if (typeof globalThis.TextDecoder === 'function') {
    const decoder = new globalThis.TextDecoder();
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i += 1) {
      bytes[i] = binary.charCodeAt(i);
    }
    return decoder.decode(bytes);
  }
  if (typeof decodeURIComponent === 'function' && typeof escape === 'function') {
    return decodeURIComponent(escape(binary));
  }
  return binary;
}

function getBtoa() {
  if (typeof globalThis !== 'undefined' && typeof globalThis.btoa === 'function') {
    return globalThis.btoa.bind(globalThis);
  }
  if (typeof Buffer !== 'undefined') {
    return (str) => Buffer.from(str, 'binary').toString('base64');
  }
  throw new Error('Base64 encoding is not supported in this environment.');
}

function getAtob() {
  if (typeof globalThis !== 'undefined' && typeof globalThis.atob === 'function') {
    return globalThis.atob.bind(globalThis);
  }
  if (typeof Buffer !== 'undefined') {
    return (str) => Buffer.from(str, 'base64').toString('binary');
  }
  throw new Error('Base64 decoding is not supported in this environment.');
}

export function encodeUnicodeBase64(value = '') {
  if (!value) return '';
  const binary = toBinaryString(value);
  const btoaFn = getBtoa();
  return btoaFn(binary);
}

export function decodeUnicodeBase64(value = '') {
  if (!value) return '';
  const atobFn = getAtob();
  const binary = atobFn(value);
  return fromBinaryString(binary);
}
