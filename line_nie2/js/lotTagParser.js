function parseLotTagInput(rawValue) {
  var text = rawValue.trim();
  var parts = text.split('|');
  if (parts.length !== 5) return null;

  return {
    prodName: parts[0].trim(),
    wo: parts[1].trim(),
    boxNo: parts[2].trim(),
    boxQty: parts[3].trim(),
    material: parts[4].trim()
  };
}
