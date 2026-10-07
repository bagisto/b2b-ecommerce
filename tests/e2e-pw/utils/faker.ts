let stampCounter = 0;

const firstNames = [
    "James",
    "Emma",
    "Liam",
    "Olivia",
    "Noah",
    "Ava",
    "William",
    "Sophia",
    "Benjamin",
    "Isabella",
    "Lucas",
    "Mia",
];

const lastNames = [
    "Smith",
    "Johnson",
    "Brown",
    "Williams",
    "Jones",
    "Garcia",
    "Miller",
    "Davis",
    "Rodriguez",
    "Martinez",
    "Hernandez",
    "Lopez",
];

function pick<T>(values: T[]): T {
    return values[Math.floor(Math.random() * values.length)];
}

export function uniqueStamp(): string {
    stampCounter = (stampCounter + 1) % 1000;

    return `${Date.now()}${String(stampCounter).padStart(3, "0")}`;
}

export function generateFirstName(): string {
    return pick(firstNames);
}

export function generateLastName(): string {
    return pick(lastNames);
}

export function generateEmail(prefix: string): string {
    return `${prefix}-${uniqueStamp()}@example.com`.toLowerCase();
}

export function generatePhoneNumber(): string {
    return `9${uniqueStamp().slice(-9)}`;
}
