const mockEd25519 = {
  getPublicKey: (seed) => new Uint8Array(32).fill(2),
  sign: async () => new Uint8Array(64).fill(3),
  verify: async () => true,
};

module.exports = {
  ...mockEd25519,
  ed25519: mockEd25519,
};
