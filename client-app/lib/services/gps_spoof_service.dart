import 'dart:async';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// v4.0.28 GPS Spoof - Combined Method 1 (Mock Location) + Method 3 (VPN Service)
/// For hiding user country, especially for Meta token creation

class GpsSpoofService {
  static const _channel = MethodChannel('com.connectix.vpn/updater');
  static const _deviceChannel = MethodChannel('com.connectix.vpn/device_info');
  
  // Preset locations for Meta and other services
  static const Map<String, Map<String, dynamic>> presets = {
    'meta_hq': {
      'name': 'Meta HQ - Menlo Park',
      'name_fa': 'دفتر اصلی متا - آمریکا',
      'lat': 37.4849,
      'lng': -122.1483,
      'country': 'US',
      'city': 'Menlo Park',
      'timezone': 'America/Los_Angeles',
      'desc': 'برای ساخت توکن متا - بهترین',
    },
    'meta_ny': {
      'name': 'Meta NY - New York',
      'name_fa': 'متا نیویورک - آمریکا',
      'lat': 40.7128,
      'lng': -74.0060,
      'country': 'US',
      'city': 'New York',
      'timezone': 'America/New_York',
      'desc': 'نیویورک آمریکا',
    },
    'google_hq': {
      'name': 'Google HQ - Mountain View',
      'name_fa': 'دفتر گوگل - آمریکا',
      'lat': 37.4220,
      'lng': -122.0841,
      'country': 'US',
      'city': 'Mountain View',
      'timezone': 'America/Los_Angeles',
      'desc': 'دفتر گوگل',
    },
    'usa_la': {
      'name': 'USA - Los Angeles',
      'name_fa': 'آمریکا - لس آنجلس',
      'lat': 34.0522,
      'lng': -118.2437,
      'country': 'US',
      'city': 'Los Angeles',
      'timezone': 'America/Los_Angeles',
      'desc': 'لس آنجلس',
    },
    'germany_berlin': {
      'name': 'Germany - Berlin',
      'name_fa': 'آلمان - برلین',
      'lat': 52.5200,
      'lng': 13.4050,
      'country': 'DE',
      'city': 'Berlin',
      'timezone': 'Europe/Berlin',
      'desc': 'برلین آلمان',
    },
    'uk_london': {
      'name': 'UK - London',
      'name_fa': 'انگلیس - لندن',
      'lat': 51.5074,
      'lng': -0.1278,
      'country': 'GB',
      'city': 'London',
      'timezone': 'Europe/London',
      'desc': 'لندن انگلیس',
    },
    'canada_toronto': {
      'name': 'Canada - Toronto',
      'name_fa': 'کانادا - تورنتو',
      'lat': 43.6532,
      'lng': -79.3832,
      'country': 'CA',
      'city': 'Toronto',
      'timezone': 'America/Toronto',
      'desc': 'تورنتو کانادا',
    },
    'netherlands_amsterdam': {
      'name': 'Netherlands - Amsterdam',
      'name_fa': 'هلند - آمستردام',
      'lat': 52.3676,
      'lng': 4.9041,
      'country': 'NL',
      'city': 'Amsterdam',
      'timezone': 'Europe/Amsterdam',
      'desc': 'آمستردام هلند',
    },
  };

  // VPN server to GPS mapping (auto sync)
  static const Map<String, Map<String, double>> vpnToGps = {
    'us': {'lat': 40.7128, 'lng': -74.0060},
    'usa': {'lat': 40.7128, 'lng': -74.0060},
    'america': {'lat': 40.7128, 'lng': -74.0060},
    'germany': {'lat': 52.5200, 'lng': 13.4050},
    'de': {'lat': 52.5200, 'lng': 13.4050},
    'uk': {'lat': 51.5074, 'lng': -0.1278},
    'england': {'lat': 51.5074, 'lng': -0.1278},
    'netherlands': {'lat': 52.3676, 'lng': 4.9041},
    'nl': {'lat': 52.3676, 'lng': 4.9041},
    'canada': {'lat': 43.6532, 'lng': -79.3832},
    'france': {'lat': 48.8566, 'lng': 2.3522},
    'singapore': {'lat': 1.3521, 'lng': 103.8198},
    'japan': {'lat': 35.6762, 'lng': 139.6503},
  };

  static Future<Map<String, dynamic>> isMockLocationEnabled() async {
    try {
      final result = await _channel.invokeMethod('isMockLocationEnabled');
      if (result is Map) {
        return Map<String, dynamic>.from(result);
      }
      return {'enabled': false, 'canMock': false};
    } catch (e) {
      return {'enabled': false, 'canMock': false, 'error': e.toString()};
    }
  }

  static Future<bool> openMockLocationSettings() async {
    try {
      await _channel.invokeMethod('openMockLocationSettings');
      return true;
    } catch (_) {
      return false;
    }
  }

  static Future<bool> setMockLocation(double lat, double lng, {double alt = 0}) async {
    try {
      await _channel.invokeMethod('setMockLocation', {
        'lat': lat,
        'lng': lng,
        'alt': alt,
      });
      // Save to prefs
      final prefs = await SharedPreferences.getInstance();
      await prefs.setDouble('mock_lat', lat);
      await prefs.setDouble('mock_lng', lng);
      await prefs.setBool('gps_spoof_enabled', true);
      return true;
    } catch (e) {
      print('setMockLocation error: $e');
      return false;
    }
  }

  static Future<bool> startMockLocation(double lat, double lng, {int interval = 2000}) async {
    try {
      await _channel.invokeMethod('startMockLocation', {
        'lat': lat,
        'lng': lng,
        'interval': interval,
      });
      final prefs = await SharedPreferences.getInstance();
      await prefs.setDouble('mock_lat', lat);
      await prefs.setDouble('mock_lng', lng);
      await prefs.setBool('gps_spoof_enabled', true);
      await prefs.setString('mock_preset', 'custom');
      return true;
    } catch (e) {
      print('startMockLocation error: $e');
      return false;
    }
  }

  static Future<bool> stopMockLocation() async {
    try {
      await _channel.invokeMethod('stopMockLocation');
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('gps_spoof_enabled', false);
      return true;
    } catch (e) {
      print('stopMockLocation error: $e');
      return false;
    }
  }

  static Future<bool> setPreset(String presetKey) async {
    final preset = presets[presetKey];
    if (preset == null) return false;
    
    final lat = preset['lat'] as double;
    final lng = preset['lng'] as double;
    
    final ok = await startMockLocation(lat, lng);
    if (ok) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('mock_preset', presetKey);
      await prefs.setString('mock_country', preset['country']);
      await prefs.setString('mock_timezone', preset['timezone']);
    }
    return ok;
  }

  static Future<bool> syncWithVpnServer(String serverName) async {
    // Auto sync GPS with VPN server location
    final lower = serverName.toLowerCase();
    for (final entry in vpnToGps.entries) {
      if (lower.contains(entry.key)) {
        final lat = entry.value['lat']!;
        final lng = entry.value['lng']!;
        return await startMockLocation(lat, lng);
      }
    }
    return false;
  }

  static Future<Map<String, dynamic>?> getCurrentMock() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final enabled = prefs.getBool('gps_spoof_enabled') ?? false;
      if (!enabled) return null;
      
      final lat = prefs.getDouble('mock_lat') ?? 0;
      final lng = prefs.getDouble('mock_lng') ?? 0;
      final preset = prefs.getString('mock_preset') ?? 'custom';
      final country = prefs.getString('mock_country') ?? 'US';
      
      if (lat == 0 && lng == 0) return null;
      
      return {
        'lat': lat,
        'lng': lng,
        'preset': preset,
        'country': country,
        'enabled': enabled,
      };
    } catch (_) {
      return null;
    }
  }

  static Future<bool> enableMetaMode() async {
    // One-click Meta mode: US IP + GPS Menlo Park + Timezone LA
    // This is the best for Meta token creation
    return await setPreset('meta_hq');
  }

  static Future<List<Map<String, dynamic>>> getPresetsList() async {
    return presets.entries.map((e) {
      return {
        'key': e.key,
        'name': e.value['name'],
        'name_fa': e.value['name_fa'],
        'lat': e.value['lat'],
        'lng': e.value['lng'],
        'country': e.value['country'],
        'city': e.value['city'],
        'timezone': e.value['timezone'],
        'desc': e.value['desc'],
      };
    }).toList();
  }
}
