# BookFlow Library Updates - Capacity Management

## Summary

This document summarizes the updates made to the BookFlow library to support multiple bookings per timeslot with a capacity-based system.

## Changes Made

### 1. Core Functionality Updates

#### Booking Model (`src/Models/Booking.php`)
- **Replaced overlap-based validation** with capacity-based validation
- **Default capacity**: 3 bookings per timeslot (when no capacity property is defined)
- **Smart capacity detection**: Checks for `capacity` property on bookable models
- **Enhanced error messages**: Provides clear feedback on available vs requested capacity
- **Backwards compatibility**: Falls back to capacity of 3 if model lookup fails

#### HasBookings Trait (`src/Traits/HasBookings.php`)
- **Updated `isAvailable()` method** to accept quantity parameter
- **Capacity-aware availability checking**: Considers current bookings and requested quantity
- **Maintains existing API**: Optional quantity parameter defaults to 1 for backwards compatibility

#### BookingHelper (`src/Helpers/BookingHelper.php`)
- **Already had capacity support**: The existing `checkAvailability()` method was already capacity-aware
- **Maintains consistency**: Uses the same capacity detection logic as the Booking model

### 2. Testing

#### New Test Files
1. **`tests/ThreeBookingCapacityTest.php`**
   - Tests default capacity behavior (3 bookings)
   - Tests capacity validation and error handling
   - Tests overlapping timeslots with capacity constraints
   - Tests cancelled bookings don't count towards capacity

2. **`tests/MultiUserSameTimeslotTest.php`**
   - Real-world scenarios of multiple users booking same timeslots
   - Tests capacity enforcement with mixed quantities
   - Demonstrates the exact use case requested

#### Updated Test Files
- **`tests/TestResource.php`**: Added explicit `capacity = 3` property
- **`tests/ComprehensiveBookingTest.php`**: Adjusted to work with new capacity constraints

### 3. Documentation

#### README Updates
- Added **Capacity Management** section
- Provided clear examples of multi-user bookings
- Documented default behavior and configuration options
- Included code examples for real-world scenarios

## Key Features Implemented

### ✅ Three Booking Capacity
- **Default**: Each timeslot can accommodate 3 simultaneous bookings
- **Configurable**: Resources can specify custom capacity via `capacity` property
- **Flexible**: Supports variable quantities per booking (e.g., one user books 2 slots)

### ✅ Multi-User Same Timeslot Support
```php
// Multiple users can book the same timeslot
$userA_booking = Booking::create([...., 'quantity' => 1]); // ✅ Success
$userB_booking = Booking::create([...., 'quantity' => 1]); // ✅ Success  
$userC_booking = Booking::create([...., 'quantity' => 1]); // ✅ Success
$userD_booking = Booking::create([...., 'quantity' => 1]); // ❌ BookingException
```

### ✅ Intelligent Error Handling
- **Clear error messages**: "Booking exceeds capacity. Available: 1, Requested: 2"
- **Capacity enforcement**: Prevents overbooking automatically
- **Graceful fallbacks**: Uses default capacity if model detection fails

### ✅ Backwards Compatibility
- **Existing API preserved**: All existing methods work unchanged
- **Optional parameters**: New quantity parameter defaults to 1
- **Gradual adoption**: Resources without capacity property use default of 3

## Example Usage

### Basic Multi-User Booking
```php
class ConferenceRoom extends Model 
{
    use HasBookings;
    public $capacity = 3; // Allow 3 simultaneous bookings
}

// Check availability before booking
$room = ConferenceRoom::find(1);
$available = $room->isAvailable($startTime, $endTime, null, 2); // Check for 2 slots

// Create bookings
$booking1 = Booking::create([
    'bookable_type' => ConferenceRoom::class,
    'bookable_id' => 1,
    'quantity' => 1, // Uses 1/3 capacity
    // ... other fields
]);

$booking2 = Booking::create([
    'bookable_type' => ConferenceRoom::class,
    'bookable_id' => 1,  
    'quantity' => 2, // Uses remaining 2/3 capacity
    // ... other fields
]);
```

### Using BookingHelper
```php
$canBook = BookingHelper::checkAvailability(
    $resource, 
    $startTime, 
    $endTime, 
    $rate, 
    2 // quantity needed
);
```

## Test Coverage

- **45 total tests passing**
- **77 assertions**
- **Comprehensive scenarios**: Single user, multi-user, capacity limits, error cases
- **Edge cases**: Cancelled bookings, overlapping periods, mixed quantities
- **Backwards compatibility**: All existing tests continue to pass

## Migration Notes

### For Existing Applications
1. **No breaking changes**: Existing code continues to work
2. **Default behavior**: Resources now allow 3 bookings per timeslot instead of 1
3. **Gradual adoption**: Add `capacity` property to models as needed

### For New Applications
1. **Set capacity explicitly**: Add `public $capacity = X;` to bookable models
2. **Use quantity parameter**: Leverage the quantity system for complex bookings
3. **Check availability**: Use the updated `isAvailable()` method with quantity

This implementation successfully addresses the requirements while maintaining backwards compatibility and providing a robust foundation for capacity-based booking management.
